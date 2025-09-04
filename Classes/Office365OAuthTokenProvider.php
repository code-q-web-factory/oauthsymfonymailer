<?php

declare(strict_types=1);

namespace CodeQ\OAuthSymfonyMailer;

use GuzzleHttp\Client;
use Neos\Cache\Exception;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cache\CacheManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use CodeQ\OAuthSymfonyMailer\Exception\OAuthTokenRequestException;
use CodeQ\OAuthSymfonyMailer\Exception\OAuthTokenResponseException;

/**
 * Provides OAuth2 access tokens for Microsoft 365 using client credentials flow.
 */
class Office365OAuthTokenProvider
{
    public const OAUTH_URL = 'https://login.microsoftonline.com/%s/oauth2/v2.0/token';
    public const SCOPE = 'https://outlook.office365.com/.default';
    public const GRANT_TYPE = 'client_credentials';
    public const CACHE_KEY = 'CodeQ_OAuthSymfonyMailer_TokenCache';

    /**
     * @Flow\InjectConfiguration(path="office365OAuthTokenProvider.tenant")
     * @var string
     */
    protected string $tenant;

    /**
     * @Flow\InjectConfiguration(path="office365OAuthTokenProvider.clientId")
     * @var string
     */
    protected string $clientId;

    /**
     * @Flow\InjectConfiguration(path="office365OAuthTokenProvider.clientSecret")
     * @var string
     */
    protected string $clientSecret;

    protected VariableFrontend $cache;
    protected ClientInterface $httpClient;
    protected Psr17Factory $psr17Factory;

    protected CacheManager $cacheManager;

    public function __construct(
        ?ClientInterface $httpClient = null,
        ?Psr17Factory $psr17Factory = null
    ) {
        $this->httpClient = $httpClient ?? new Client();
        $this->psr17Factory = $psr17Factory ?? new Psr17Factory();
    }

    /**
     * Method injection by Flow for the cache manager.
     */
    public function injectCacheManager(CacheManager $cacheManager): void
    {
        $this->cacheManager = $cacheManager;
        /** @var VariableFrontend $cache */
        $cache = $cacheManager->getCache(self::CACHE_KEY);
        $this->cache = $cache;
    }

    public function initializeObject(): void
    {
        if (!isset($this->httpClient)) {
            $this->httpClient = new Client();
        }
        if (!isset($this->psr17Factory)) {
            $this->psr17Factory = new Psr17Factory();
        }
        if (!isset($this->cache)) {
            /** @var VariableFrontend $cache */
            $cache = $this->cacheManager->getCache(self::CACHE_KEY);
            $this->cache = $cache;
        }
    }

    /**
     * Returns an access token, using cache when possible.
     */
    public function getToken(): string
    {
        $cacheKey = 'access_token';
        $token = $this->cache->get($cacheKey);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        [$token, $ttl] = $this->fetchToken();
        // store with a safety margin
        try {
            $this->cache->set($cacheKey, $token, [], max(1, $ttl - 30));
        } catch (Exception $e) {
            // do not fail if caching doesn't work
        }

        return $token;
    }

    /**
     * Fetches a new token from Microsoft and returns [token, expiresInSeconds].
     *
     * @return array{0:string,1:int}
     */
    public function fetchToken(): array
    {
        $url = sprintf(self::OAUTH_URL, $this->tenant);

        $bodyParams = http_build_query([
            'client_id' => $this->clientId,
            'scope' => self::SCOPE,
            'client_secret' => $this->clientSecret,
            'grant_type' => self::GRANT_TYPE,
        ], '', '&');

        $request = $this->psr17Factory
            ->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $request = $request->withBody($this->psr17Factory->createStream($bodyParams));

        $response = $this->httpClient->sendRequest($request);

        $this->ensureSuccessfulResponse($response);

        $data = json_decode((string)$response->getBody(), true);
        if (!is_array($data) || !isset($data['access_token'])) {
            throw OAuthTokenResponseException::missingAccessToken();
        }

        $token = (string)$data['access_token'];
        $expiresIn = isset($data['expires_in']) ? (int)$data['expires_in'] : 3600;

        return [$token, $expiresIn];
    }

    private function ensureSuccessfulResponse(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $body = (string)$response->getBody();
            throw OAuthTokenRequestException::fromHttp($status, $body);
        }
    }
}
