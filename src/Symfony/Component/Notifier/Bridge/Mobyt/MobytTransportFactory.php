<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mobyt;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Bastien Durand <bdurand-dev@outlook.com>
 *
 * @experimental in 5.3
 */
final class MobytTransportFactory extends AbstractTransportFactory
{
    /**
     * @return MobytTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $accountSid = $this->getUser($dsn);
        $authToken = $this->getPassword($dsn);
        $from = $dsn->getOption('from');
        $typeQuality = $dsn->getOption('type_quality', MobytOptions::MESSAGE_TYPE_QUALITY_LOW);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        if ('mobyt' === $scheme) {
            return (new MobytTransport($accountSid, $authToken, $from, $typeQuality, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
        }

        throw new UnsupportedSchemeException($dsn, 'mobyt', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['mobyt'];
    }
}
