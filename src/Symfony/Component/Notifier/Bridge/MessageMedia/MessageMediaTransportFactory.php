<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MessageMedia;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Adrian Nguyen <vuphuong87@gmail.com>
 */
final class MessageMediaTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): MessageMediaTransport
    {
        $scheme = $dsn->getScheme();

        if ('messagemedia' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'messagemedia', $this->getSupportedSchemes());
        }

        $apiKey = $this->getUser($dsn);
        $apiSecret = $this->getPassword($dsn);
        $from = $dsn->getOption('from');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new MessageMediaTransport($apiKey, $apiSecret, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['messagemedia'];
    }
}
