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

use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Emanuele Panzeri <thepanz@gmail.com>
 */
final class MattermostTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('mattermost' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'mattermost', $this->getSupportedSchemes());
        }

        $path = $dsn->getPath();
        $token = $this->getUser($dsn);
        $channel = $dsn->getOption('channel');

        if (!$channel) {
            throw new IncompleteDsnException('Missing channel.', $dsn->getOriginalDsn());
        }

        $host = $dsn->getHost();
        $port = $dsn->getPort();

        return (new MattermostTransport($token, $channel, $path, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['mattermost'];
    }
}
