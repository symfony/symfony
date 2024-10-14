<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LineBot;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Yi-Jyun Pan <me@pan93.com>
 */
final class LineBotTransportFactory extends AbstractTransportFactory
{
    private const SCHEME = 'linebot';

    protected function getSupportedSchemes(): array
    {
        return [self::SCHEME];
    }

    public function create(Dsn $dsn): LineBotTransport
    {
        if (self::SCHEME !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, self::SCHEME, $this->getSupportedSchemes());
        }

        $accessToken = $this->getUser($dsn);
        $receiver = $dsn->getRequiredOption('receiver');
        if (!\is_string($receiver)) {
            throw new InvalidArgumentException('The "receiver" option must be a string.');
        }

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new LineBotTransport($accessToken, $receiver, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }
}
