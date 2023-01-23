<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\PagerDuty;

use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Joseph Bielawski <stloyd@gmail.com>
 */
final class PagerDutyTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): PagerDutyTransport
    {
        $scheme = $dsn->getScheme();

        if ('pagerduty' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'pagerduty', $this->getSupportedSchemes());
        }

        $apiToken = $this->getUser($dsn);
        $host = $this->getHost($dsn);

        return (new PagerDutyTransport($apiToken, $this->client, $this->dispatcher))->setHost($host);
    }

    protected function getSupportedSchemes(): array
    {
        return ['pagerduty'];
    }

    private function getHost(Dsn $dsn): string
    {
        $host = $dsn->getHost();
        if ('default' === $host) {
            throw new IncompleteDsnException('Host is not set.', 'pagerduty://default');
        }

        if (!str_ends_with($host, '.pagerduty.com')) {
            throw new IncompleteDsnException('Host must be in format: "subdomain.pagerduty.com".', 'pagerduty://'.$host);
        }

        return $host;
    }
}
