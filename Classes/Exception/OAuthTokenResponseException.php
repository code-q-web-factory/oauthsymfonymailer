<?php

declare(strict_types=1);

namespace CodeQ\OAuthSymfonyMailer\Exception;

class OAuthTokenResponseException extends \RuntimeException
{
    public static function missingAccessToken(): self
    {
        return new self('OAuth token response is invalid: missing access_token.');
    }
}
