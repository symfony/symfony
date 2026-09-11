<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LineNotify;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Akira Kurozumi <info@a-zumi.net>
 *
 * @deprecated since Symfony 8.2, use the LineBot bridge instead
 */
final class LineNotifyTransportFactory extends AbstractTransportFactory
{
    private const SCHEME = 'linenotify';

    protected function getSupportedSchemes(): array
    {
        return [self::SCHEME];
    }

    public function create(Dsn $dsn): LineNotifyTransport
    {
        trigger_deprecation('symfony/line-notify-notifier', '8.2', 'The "symfony/line-notify-notifier" package is deprecated as LINE Notify was shut down, use "symfony/line-bot-notifier" instead.');

        if (self::SCHEME !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, self::SCHEME, $this->getSupportedSchemes());
        }

        $token = $this->getUser($dsn);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new LineNotifyTransport($token, $this->client, $this->dispatcher))->setHost($host)->setPort($port)->setSsl($this->getSsl($dsn));
    }
}
