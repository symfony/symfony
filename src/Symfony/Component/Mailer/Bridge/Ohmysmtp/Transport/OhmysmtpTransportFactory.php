<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Ohmysmtp\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Paul Oms <support@ohmysmtp.com>
 */
final class OhmysmtpTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $user = $this->getUser($dsn);

        if ('ohmysmtp+api' === $scheme) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();

            return (new OhmysmtpApiTransport($user, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
        }

        if ('ohmysmtp+smtp' === $scheme || 'ohmysmtp+smtps' === $scheme || 'ohmysmtp' === $scheme) {
            return new OhmysmtpSmtpTransport($user, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'ohmysmtp', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['ohmysmtp', 'ohmysmtp+api', 'ohmysmtp+smtp', 'ohmysmtp+smtps'];
    }
}
