<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Slack;

use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.1
 */
final class SlackTransportFactory extends AbstractTransportFactory
{
    /**
     * @return SlackTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $id = ltrim($dsn->getPath(), '/');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        if (!$id) {
            throw new IncompleteDsnException('Missing path (maybe you haven\'t update the DSN when upgrading from 5.0).', $dsn->getOriginalDsn());
        }

        if ('slack' === $scheme) {
            return (new SlackTransport($id, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
        }

        throw new UnsupportedSchemeException($dsn, 'slack', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['slack'];
    }
}
