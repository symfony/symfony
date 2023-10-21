<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\OhMySmtp\Transport;

use Symfony\Component\Mailer\Bridge\MailPace\Transport\MailPaceTransportFactory;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Paul Oms <support@ohmysmtp.com>
 *
 * @deprecated since Symfony 6.2, use MailPaceTransportFactory instead
 */
final class OhMySmtpTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        trigger_deprecation('symfony/oh-my-smtp-mailer', '6.2', 'The "%s" class is deprecated, use "%s" instead.', self::class, MailPaceTransportFactory::class);

        $scheme = $dsn->getScheme();

        if ('ohmysmtp+api' === $scheme) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();

            return (new OhMySmtpApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
        }

        if ('ohmysmtp+smtp' === $scheme || 'ohmysmtp+smtps' === $scheme || 'ohmysmtp' === $scheme) {
            return new OhMySmtpSmtpTransport($this->getUser($dsn), $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'ohmysmtp', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['ohmysmtp', 'ohmysmtp+api', 'ohmysmtp+smtp', 'ohmysmtp+smtps'];
    }
}
