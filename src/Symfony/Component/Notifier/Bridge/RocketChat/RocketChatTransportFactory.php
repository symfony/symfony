<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\RocketChat;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Jeroen Spee <https://github.com/Jeroeny>
 */
final class RocketChatTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): RocketChatTransport
    {
        $scheme = $dsn->getScheme();

        if ('rocketchat' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'rocketchat', $this->getSupportedSchemes());
        }

        $accessToken = $this->getUser($dsn);
        $channel = $dsn->getOption('channel');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new RocketChatTransport($accessToken, $channel, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['rocketchat'];
    }
}
