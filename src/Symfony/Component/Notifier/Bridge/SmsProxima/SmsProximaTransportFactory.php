<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsProxima;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author SMS Proxima <contact@sms-proxima.com>
 */
final class SmsProximaTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('sms-proxima' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'sms-proxima', $this->getSupportedSchemes());
        }

        $token = $this->getUser($dsn);
        $from = $dsn->getOption('from', '');

        return (new SmsProximaTransport($token, $from, $this->client, $this->dispatcher))
            ->setHost($dsn->getHost())
            ->setPort($dsn->getPort())
            ->setSsl($this->getSsl($dsn));
    }

    protected function getSupportedSchemes(): array
    {
        return ['sms-proxima'];
    }
}
