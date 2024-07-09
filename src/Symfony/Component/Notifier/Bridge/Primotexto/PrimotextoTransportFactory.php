<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Primotexto;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Samaël Tomas <samael.tomas@gmail.com>
 */
final class PrimotextoTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): PrimotextoTransport
    {
        $scheme = $dsn->getScheme();

        if ('primotexto' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'primotexto', $this->getSupportedSchemes());
        }

        $apiKey = $this->getUser($dsn);
        $from = $dsn->getOption('from');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new PrimotextoTransport($apiKey, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['primotexto'];
    }
}
