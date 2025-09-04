<?php

declare(strict_types=1);

namespace CodeQ\OAuthSymfonyMailer;

use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Transport factory to support oauth:// DSN and use XOAUTH2 with Office365.
 */
class OAuthEsmtpTransportFactory implements TransportFactoryInterface
{
    protected ?EsmtpTransportFactory $inner = null;

    protected XOAuth2Authenticator $authenticator;

    /**
     * Method injection by Flow for the inner factory.
     */
    public function injectInner(EsmtpTransportFactory $inner): void
    {
        $this->inner = $inner;
    }

    /**
     * Method injection by Flow for the authenticator.
     */
    public function injectAuthenticator(XOAuth2Authenticator $authenticator): void
    {
        $this->authenticator = $authenticator;
    }

    public function create(Dsn $dsn): TransportInterface
    {
        // Map host aliases if needed
        $host = $dsn->getHost();
        $username = $dsn->getUser();

        if ($host === 'office365') {
            $host = 'smtp.office365.com';
        }

        // Ensure inner factory exists if not injected (fallback for non-Flow instantiation)
        $inner = $this->inner ?? new EsmtpTransportFactory();

        // Always use STARTTLS on 587 for Office 365
        $smtpDsn = new Dsn('smtp', $host, $username, null, 587, ['tls' => 'tls']);
        $transport = $inner->create($smtpDsn);

        if ($transport instanceof EsmtpTransport) {
            $transport->setAuthenticators([$this->authenticator]);
            // Username is required for XOAUTH2 payload
            if ($username !== null) {
                $transport->setUsername($username);
            }
        }

        return $transport;
    }

    public function supports(Dsn $dsn): bool
    {
        return $dsn->getScheme() === 'oauth';
    }
}
