<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OrangeSms;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

final class OrangeSmsTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): OrangeSmsTransport
    {
        $scheme = $dsn->getScheme();

        if ('orange-sms' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'orange-sms', $this->getSupportedSchemes());
        }

        $user = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $from = $dsn->getRequiredOption('from');
        $senderName = $dsn->getOption('sender_name');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new OrangeSmsTransport($user, $password, $from, $senderName, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['orange-sms'];
    }
}
