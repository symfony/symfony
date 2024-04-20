<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsFactor;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Thibault Buathier <thibault.buathier@gmail.com>
 */
final class SmsFactorTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): SmsFactorTransport
    {
        $scheme = $dsn->getScheme();

        if ('sms-factor' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'sms-factor', $this->getSupportedSchemes());
        }

        $tokenApi = $this->getUser($dsn);
        $sender = $dsn->getOption('sender');
        $pushType = $this->getPushType($dsn);

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new SmsFactorTransport($tokenApi, $sender, $pushType, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['sms-factor'];
    }

    private function getPushType(Dsn $dsn): ?SmsFactorPushType
    {
        $pushType = $dsn->getOption('push_type');

        if (!\is_string($pushType)) {
            return null;
        }

        return SmsFactorPushType::tryFrom($pushType);
    }
}
