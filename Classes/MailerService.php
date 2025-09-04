<?php

declare(strict_types=1);

namespace CodeQ\OAuthSymfonyMailer;

use Neos\Flow\Annotations as Flow;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;

class MailerService
{
    /**
     * @var array{dsn: string}
     * @Flow\InjectConfiguration(path="mailer")
     */
    protected array $mailerConfiguration;

    /**
     * @Flow\Inject
     * @var OAuthEsmtpTransportFactory
     */
    protected OAuthEsmtpTransportFactory $oauthEsmtpTransportFactory;

    /**
     * Returns a mailer instance with the given transport or the configured default transport.
     *
     * @param TransportInterface|null $transport
     * @return Mailer
     * @throws InvalidMailerConfigurationException
     */
    public function getMailer(TransportInterface $transport = null): Mailer
    {
        if ($transport !== null) {
            return new Mailer($transport);
        }

        // throw exception when dsn is not set
        if (!isset($this->mailerConfiguration['dsn'])) {
            throw new InvalidMailerConfigurationException('No DSN configured for CodeQ.OAuthSymfonyMailer', 1756977526077);
        }

        $dsnString = $this->mailerConfiguration['dsn'];
        $dsn = Dsn::fromString($dsnString);

        if ($this->oauthEsmtpTransportFactory->supports($dsn)) {
            $transport = $this->oauthEsmtpTransportFactory->create($dsn);
        } else {
            $transport = Transport::fromDsn($dsnString);
        }

        return new Mailer($transport);
    }
}
