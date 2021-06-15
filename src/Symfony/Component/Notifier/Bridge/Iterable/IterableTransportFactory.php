<?php

declare(strict_types = 1);

namespace Symfony\Component\Notifier\Bridge\Iterable;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Balázs Csaba <csaba.balazs@lingoda.com>
 */
final class IterableTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('iterable' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'iterable', $this->getSupportedSchemes());
        }

        $apiKey = $this->getUser($dsn);
        $campaignId = $dsn->getOption('campaign_id');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new IterableTransport($apiKey, $campaignId, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['iterable'];
    }
}
