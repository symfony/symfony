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

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class SlackTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): SlackTransport
    {
        if ('slack' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'slack', $this->getSupportedSchemes());
        }

        $accessToken = $this->getUser($dsn);
        $channel = $dsn->getOption('channel');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new SlackTransport($accessToken, $channel, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['slack'];
    }
}
