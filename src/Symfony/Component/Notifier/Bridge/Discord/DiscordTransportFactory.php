<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Discord;

use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Mathieu Piot <math.piot@gmail.com>
 */
final class DiscordTransportFactory extends AbstractTransportFactory
{
    /**
     * @return DiscordTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('discord' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'discord', $this->getSupportedSchemes());
        }

        $token = $this->getUser($dsn);
        $webhookId = $dsn->getOption('webhook_id');

        if (!$webhookId) {
            throw new IncompleteDsnException('Missing webhook_id.', $dsn->getOriginalDsn());
        }

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new DiscordTransport($token, $webhookId, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['discord'];
    }
}
