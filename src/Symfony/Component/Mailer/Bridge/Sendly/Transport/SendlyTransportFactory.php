<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendly\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Amin Dhouib <amin@devino.ca>
 */
final class SendlyTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('sendly' === $scheme || 'sendly+api' === $scheme) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();

            return (new SendlyApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))
                ->setHost($host)
                ->setPort($dsn->getPort());
        }

        throw new UnsupportedSchemeException($dsn, 'sendly', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['sendly', 'sendly+api'];
    }
}
