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
 */
final class OvhCloudTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('ovhcloud' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'ovhcloud', $this->getSupportedSchemes());
        }

        $applicationKey = $this->getUser($dsn);
        $applicationSecret = $this->getPassword($dsn);
        $consumerKey = $dsn->getRequiredOption('consumer_key');
        $serviceName = $dsn->getRequiredOption('service_name');
        $sender = $dsn->getOption('sender');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new OvhCloudTransport($applicationKey, $applicationSecret, $consumerKey, $serviceName, $this->client, $this->dispatcher))->setHost($host)->setPort($port)->setSender($sender);
    }

    protected function getSupportedSchemes(): array
    {
        return ['ovhcloud'];
    }
}
