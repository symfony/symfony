<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Gitter;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Christin Gruber <c.gruber@touchdesign.de>
 */
final class GitterTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): GitterTransport
    {
        $scheme = $dsn->getScheme();

        if ('gitter' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'gitter', $this->getSupportedSchemes());
        }

        $token = $this->getUser($dsn);
        $roomId = $dsn->getRequiredOption('room_id');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new GitterTransport($token, $roomId, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['gitter'];
    }
}
