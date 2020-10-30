<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telnyx;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Mihail Krasilnikov <mihail.krasilnikov.j@gmail.com>
 */
final class TelnyxTransportFactory extends AbstractTransportFactory
{
    /**
     * @return TelnyxTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('telnyx' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'telnyx', $this->getSupportedSchemes());
        }

        $apiKey = $this->getUser($dsn);
        $from = $dsn->getRequiredOption('from');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new TelnyxTransport($apiKey, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['telnyx'];
    }
}
