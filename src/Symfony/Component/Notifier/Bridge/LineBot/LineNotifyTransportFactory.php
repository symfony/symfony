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

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * * @author Yi-Jyun Pan <me@pan93.com>
 */
final class LineNotifyTransportFactory extends AbstractTransportFactory
{
    private const SCHEME = 'linebot';

    protected function getSupportedSchemes(): array
    {
        return [self::SCHEME];
    }

    public function create(Dsn $dsn): LineNotifyTransport
    {
        if (self::SCHEME !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, self::SCHEME, $this->getSupportedSchemes());
        }

        $token = $this->getUser($dsn);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new LineNotifyTransport($token, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }
}
