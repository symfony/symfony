<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\RingCentral;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class RingCentralTransportFactory extends AbstractTransportFactory
{
    private const TRANSPORT_SCHEME = 'ringcentral';

    public function create(Dsn $dsn): RingCentralTransport
    {
        $scheme = $dsn->getScheme();
        if (self::TRANSPORT_SCHEME !== $scheme) {
            throw new UnsupportedSchemeException($dsn, self::TRANSPORT_SCHEME, $this->getSupportedSchemes());
        }
        $apiKey = $this->getUser($dsn);
        $from = $dsn->getRequiredOption('from');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new RingCentralTransport($apiKey, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return [self::TRANSPORT_SCHEME];
    }
}
