<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Zulip;

use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Mohammad Emran Hasan <phpfour@gmail.com>
 */
final class ZulipTransportFactory extends AbstractTransportFactory
{
    /**
     * @return ZulipTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('zulip' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'zulip', $this->getSupportedSchemes());
        }

        $email = $this->getUser($dsn);
        $token = $this->getPassword($dsn);
        $channel = $dsn->getOption('channel');

        if (!$channel) {
            throw new IncompleteDsnException('Missing channel.', $dsn->getOriginalDsn());
        }

        $host = $dsn->getHost();
        $port = $dsn->getPort();

        return (new ZulipTransport($email, $token, $channel, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['zulip'];
    }
}
