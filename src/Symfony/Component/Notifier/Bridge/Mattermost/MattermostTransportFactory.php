<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mattermost;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Emanuele Panzeri <thepanz@gmail.com>
 *
 * @experimental in 5.1
 */
final class MattermostTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $token = $this->getUser($dsn);
        $channel = $dsn->getOption('channel');
        $host = $dsn->getHost();
        $port = $dsn->getPort();

        if ('mattermost' === $scheme) {
            return (new MattermostTransport($token, $channel, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
        }

        throw new UnsupportedSchemeException($dsn, 'mattermost', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['mattermost'];
    }
}
