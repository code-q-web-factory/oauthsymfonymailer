<?php

declare(strict_types=1);

namespace CodeQ\OAuthSymfonyMailer;

use Symfony\Component\Mailer\Transport\Smtp\Auth\AuthenticatorInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * XOAUTH2 authenticator that uses a token provided by Office365OAuthTokenProvider.
 */
class XOAuth2Authenticator implements AuthenticatorInterface
{
    protected Office365OAuthTokenProvider $tokenProvider;

    /**
     * Method injection by Flow for the token provider.
     */
    public function injectTokenProvider(Office365OAuthTokenProvider $provider): void
    {
        $this->tokenProvider = $provider;
    }

    public function getAuthKeyword(): string
    {
        return 'XOAUTH2';
    }

    public function authenticate(EsmtpTransport $client): void
    {
        $username = (string)$client->getUsername();
        $token = $this->tokenProvider->getToken();

        $auth = base64_encode(sprintf("user=%s\x01auth=Bearer %s\x01\x01", $username, $token));

        // 235 is authentication successful response code for SMTP
        $client->executeCommand("AUTH XOAUTH2 $auth\r\n", [235]);
    }
}
