<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Clickatell;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Kevin Auvinet <k.auvinet@gmail.com>
 */
final class ClickatellTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): ClickatellTransport
    {
        $scheme = $dsn->getScheme();

        if ('clickatell' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'clickatell', $this->getSupportedSchemes());
        }

        $authToken = $this->getUser($dsn);
        $from = $dsn->getOption('from');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new ClickatellTransport($authToken, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['clickatell'];
    }
}
