<?php

declare(strict_types=1);

namespace CodeQ\OAuthSymfonyMailer\Tests\Functional;

use CodeQ\OAuthSymfonyMailer\OAuthEsmtpTransportFactory;
use CodeQ\OAuthSymfonyMailer\Office365OAuthTokenProvider;
use CodeQ\OAuthSymfonyMailer\XOAuth2Authenticator;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Cache\CacheManager;
use Neos\Flow\Tests\FunctionalTestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Assert;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\TransportInterface;

class OAuthEsmtpTransportFactoryTest extends FunctionalTestCase
{
    private function buildFactory(): OAuthEsmtpTransportFactory
    {
        $psr17 = new Psr17Factory();
        $http = $this->createMock(ClientInterface::class);
        $cacheManager = $this->createMock(CacheManager::class);
        $cache = $this->createMock(VariableFrontend::class);
        $cacheManager->method('getCache')->willReturn($cache);

        $provider = new Office365OAuthTokenProvider($http, $psr17);
        $provider->injectCacheManager($cacheManager);
        // Inject minimal config so provider can be constructed safely
        $this->setProtectedProperty($provider, 'tenant', 'tenant');
        $this->setProtectedProperty($provider, 'clientId', 'id');
        $this->setProtectedProperty($provider, 'clientSecret', 'secret');

        $authenticator = new XOAuth2Authenticator();
        $authenticator->injectTokenProvider($provider);
        $esmtpFactory = new EsmtpTransportFactory();

        $factory = new OAuthEsmtpTransportFactory();
        $factory->injectInner($esmtpFactory);
        $factory->injectAuthenticator($authenticator);
        return $factory;
    }

    private function setProtectedProperty(object $object, string $property, mixed $value): void
    {
        $ref = new \ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }

    /** @test */
    public function supports_returns_true_only_for_oauth_scheme(): void
    {
        $factory = $this->buildFactory();

        $oauthDsn = new Dsn('oauth', 'office365', 'user@example.com');
        $smtpDsn = new Dsn('smtp', 'smtp.office365.com');

        Assert::assertTrue($factory->supports($oauthDsn));
        Assert::assertFalse($factory->supports($smtpDsn));
    }

    /** @test */
    public function create_builds_esmtp_transport_with_username_for_office365_host_alias(): void
    {
        $factory = $this->buildFactory();

        $dsn = new Dsn('oauth', 'office365', 'user@example.com');
        $transport = $factory->create($dsn);

        Assert::assertInstanceOf(TransportInterface::class, $transport);
        Assert::assertInstanceOf(EsmtpTransport::class, $transport);
        // Username must be set for XOAUTH2 payload
        Assert::assertSame('user@example.com', $transport->getUsername());
    }
}
