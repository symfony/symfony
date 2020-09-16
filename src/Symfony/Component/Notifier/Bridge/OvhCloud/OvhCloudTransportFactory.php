<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OvhCloud;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Thomas Ferney <thomas.ferney@gmail.com>
 *
 * @experimental in 5.1
 */
final class OvhCloudTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $applicationKey = $this->getUser($dsn);
        $applicationSecret = $this->getPassword($dsn);
        $consumerKey = $dsn->getOption('consumer_key');
        $serviceName = $dsn->getOption('service_name');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        if ('ovhcloud' === $scheme) {
            return (new OvhCloudTransport($applicationKey, $applicationSecret, $consumerKey, $serviceName, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
        }

        throw new UnsupportedSchemeException($dsn, 'ovhcloud', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['ovhcloud'];
    }
}
