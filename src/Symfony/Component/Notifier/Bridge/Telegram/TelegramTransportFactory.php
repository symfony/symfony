<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telegram;

use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class TelegramTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TelegramTransport
    {
        if ('telegram' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'telegram', $this->getSupportedSchemes());
        }

        $token = $this->getToken($dsn);
        $channel = $dsn->getOption('channel');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();
        $disableHttps = filter_var($dsn->getOption('disable_https'), FILTER_VALIDATE_BOOLEAN);

        return (new TelegramTransport($token, $channel, $this->client, $this->dispatcher, $disableHttps))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['telegram'];
    }

    private function getToken(Dsn $dsn): string
    {
        if (null === $dsn->getUser() && null === $dsn->getPassword()) {
            throw new IncompleteDsnException('Missing token.', 'telegram://'.$dsn->getHost());
        }

        if (null === $dsn->getPassword()) {
            throw new IncompleteDsnException('Malformed token.', 'telegram://'.$dsn->getHost());
        }

        return \sprintf('%s:%s', $dsn->getUser(), $dsn->getPassword());
    }
}
