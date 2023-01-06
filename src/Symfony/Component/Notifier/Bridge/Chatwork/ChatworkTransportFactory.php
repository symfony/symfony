<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Chatwork;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Ippei Sumida <ippey.s@gmail.com
 */
class ChatworkTransportFactory extends AbstractTransportFactory
{
    private const SCHEME = 'chatwork';

    protected function getSupportedSchemes(): array
    {
        return [self::SCHEME];
    }

    public function create(Dsn $dsn): ChatworkTransport
    {
        $scheme = $dsn->getScheme();
        if (self::SCHEME !== $scheme) {
            throw new UnsupportedSchemeException($dsn, self::SCHEME, $this->getSupportedSchemes());
        }

        $token = $this->getUser($dsn);
        $roomId = $dsn->getRequiredOption('room_id');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new ChatworkTransport($token, $roomId, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }
}
