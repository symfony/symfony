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

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Mathieu Piot <math.piot@gmail.com>
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
final class DiscordTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('discord' === $scheme) {
            $token = $this->getUser($dsn);
            $webhookId = $dsn->getRequiredOption('webhook_id');
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();

            return (new DiscordTransport($token, $webhookId, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
        }

        if ('discord+bot' === $scheme) {
            $token = $this->getUser($dsn);
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();

            return (new DiscordBotTransport($token, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
        }

        throw new UnsupportedSchemeException($dsn, 'discord', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['discord', 'discord+bot'];
    }
}
