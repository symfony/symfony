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

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Mohammad Emran Hasan <phpfour@gmail.com>
 *
 * @experimental in 5.3
 */
class ZulipTransportFactory extends AbstractTransportFactory
{
    /**
     * {@inheritdoc}
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $email = $this->getUser($dsn);
        $token = $this->getPassword($dsn);
        $channel = $dsn->getOption('channel');
        $host = $dsn->getHost();
        $port = $dsn->getPort();

        if ('zulip' === $scheme) {
            return (new ZulipTransport($email, $token, $channel, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
        }

        throw new UnsupportedSchemeException($dsn, 'zulip', $this->getSupportedSchemes());
    }

    /**
     * {@inheritdoc}
     */
    protected function getSupportedSchemes(): array
    {
        return ['zulip'];
    }
}
