<?php

declare(strict_types=1);

namespace CodeQ\OAuthSymfonyMailer\Tests\Unit;

use CodeQ\OAuthSymfonyMailer\Office365OAuthTokenProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Cache\CacheManager;

class Office365OAuthTokenProviderTest extends TestCase
{
    private Psr17Factory $psr17Factory;
    /** @var ClientInterface&MockObject */
    private ClientInterface $httpClient;
    /** @var CacheManager&MockObject */
    private CacheManager $cacheManager;
    /** @var VariableFrontend&MockObject */
    private VariableFrontend $cache;

    protected function setUp(): void
    {
        $this->psr17Factory = new Psr17Factory();
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->cache = $this->createMock(VariableFrontend::class);

        $this->cacheManager->method('getCache')->with(Office365OAuthTokenProvider::CACHE_KEY)->willReturn($this->cache);
    }

    private function createProvider(): Office365OAuthTokenProvider
    {
        $provider = new Office365OAuthTokenProvider($this->httpClient, $this->psr17Factory);
        $provider->injectCacheManager($this->cacheManager);
        // Inject configuration via reflection
        $this->setProtectedProperty($provider, 'tenant', 'my-tenant');
        $this->setProtectedProperty($provider, 'clientId', 'my-client-id');
        $this->setProtectedProperty($provider, 'clientSecret', 'my-client-secret');
        return $provider;
    }

    private function setProtectedProperty(object $object, string $property, mixed $value): void
    {
        $ref = new \ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }

    public function testGetTokenReturnsCachedToken(): void
    {
        $this->cache->expects($this->once())
            ->method('get')
            ->with('access_token')
            ->willReturn('cached_token');

        $this->httpClient->expects($this->never())->method('sendRequest');

        $provider = $this->createProvider();
        $this->assertSame('cached_token', $provider->getToken());
    }

    public function testGetTokenCachesNewTokenWithSafetyMargin(): void
    {
        $this->cache->expects($this->once())
            ->method('get')
            ->with('access_token')
            ->willReturn(null);

        // Prepare HTTP response with token
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($this->psr17Factory->createStream(json_encode([
            'access_token' => 'new_token',
            'expires_in' => 120,
        ])));

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (RequestInterface $req): bool {
                // Basic checks: POST and correct URL
                return $req->getMethod() === 'POST'
                    && (string)$req->getUri() === sprintf(Office365OAuthTokenProvider::OAUTH_URL, 'my-tenant')
                    && $req->hasHeader('Content-Type');
            }))
            ->willReturn($response);

        // Expect cache set with ttl-30
        $this->cache->expects($this->once())
            ->method('set')
            ->with(
                'access_token',
                'new_token',
                $this->isType('array'),
                $this->equalTo(90) // 120 - 30 safety margin
            );

        $provider = $this->createProvider();
        $this->assertSame('new_token', $provider->getToken());
    }

    public function testFetchTokenMakesRequestAndParsesResponse(): void
    {
        $body = [
            'access_token' => 'abc123',
            'expires_in' => 3600,
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($this->psr17Factory->createStream(json_encode($body)));

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (RequestInterface $req): bool {
                if ($req->getMethod() !== 'POST') {
                    return false;
                }
                // Ensure form URL encoded header is present
                $hasHeader = $req->hasHeader('Content-Type') && in_array('application/x-www-form-urlencoded', $req->getHeader('Content-Type'), true);
                // Ensure body contains grant_type
                $contents = (string)$req->getBody();
                return $hasHeader && strpos($contents, 'grant_type=client_credentials') !== false;
            }))
            ->willReturn($response);

        $provider = $this->createProvider();
        [$token, $ttl] = $provider->fetchToken();

        $this->assertSame('abc123', $token);
        $this->assertSame(3600, $ttl);
    }

    public function testFetchTokenThrowsOnErrorResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(400);
        $response->method('getBody')->willReturn($this->psr17Factory->createStream('bad request'));

        $this->httpClient->expects($this->once())->method('sendRequest')->willReturn($response);

        $provider = $this->createProvider();

        $this->expectException(\CodeQ\OAuthSymfonyMailer\Exception\OAuthTokenRequestException::class);
        $this->expectExceptionMessage('OAuth token request failed (HTTP 400)');
        $provider->fetchToken();
    }

    public function testFetchTokenThrowsWhenAccessTokenMissing(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($this->psr17Factory->createStream(json_encode(['foo' => 'bar'])));

        $this->httpClient->expects($this->once())->method('sendRequest')->willReturn($response);

        $provider = $this->createProvider();

        $this->expectException(\CodeQ\OAuthSymfonyMailer\Exception\OAuthTokenResponseException::class);
        $this->expectExceptionMessage('missing access_token');
        $provider->fetchToken();
    }
}
