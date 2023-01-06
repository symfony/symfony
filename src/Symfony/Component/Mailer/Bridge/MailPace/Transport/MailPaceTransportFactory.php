<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailPace\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Paul Oms <support@mailpace.com>
 */
final class MailPaceTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('mailpace+api' === $scheme) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();

            return (new MailPaceApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
        }

        if ('mailpace+smtp' === $scheme || 'mailpace+smtps' === $scheme || 'mailpace' === $scheme) {
            return new MailPaceSmtpTransport($this->getUser($dsn), $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'mailpace', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['mailpace', 'mailpace+api', 'mailpace+smtp', 'mailpace+smtps'];
    }
}
