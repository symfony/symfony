<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sipgate;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Lukas Kaltenbach <lk@wikanet.de>
 */
final class SipgateTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): SipgateTransport
    {
        if ('sipgate' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'sipgate', $this->getSupportedSchemes());
        }

        $tokenId = $this->getUser($dsn);
        $token = $this->getPassword($dsn);
        $senderId = $dsn->getRequiredOption('senderId');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new SipgateTransport($tokenId, $token, $senderId, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['sipgate'];
    }
}
