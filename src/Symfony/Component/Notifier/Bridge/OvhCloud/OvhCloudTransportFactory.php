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

/**
 * @author Thomas Ferney <thomas.ferney@gmail.com>
 */
final class OvhCloudTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): OvhCloudTransport
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
        $noStopClause = filter_var($dsn->getOption('no_stop_clause', false), \FILTER_VALIDATE_BOOL);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new OvhCloudTransport($applicationKey, $applicationSecret, $consumerKey, $serviceName, $this->client, $this->dispatcher))->setHost($host)->setPort($port)->setSender($sender)->setNoStopClause($noStopClause);
    }

    protected function getSupportedSchemes(): array
    {
        return ['ovhcloud'];
    }
}
