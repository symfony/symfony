<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SpotHit;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author James Hemery <james@yieldstudio.fr>
 */
final class SpotHitTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): SpotHitTransport
    {
        $scheme = $dsn->getScheme();

        if ('spothit' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'spothit', $this->getSupportedSchemes());
        }

        $token = $this->getUser($dsn);
        $from = $dsn->getOption('from');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new SpotHitTransport($token, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['spothit'];
    }
}
