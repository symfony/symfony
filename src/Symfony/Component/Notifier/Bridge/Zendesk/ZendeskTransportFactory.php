<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Zendesk;

use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Joseph Bielawski <stloyd@gmail.com>
 */
final class ZendeskTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): ZendeskTransport
    {
        $scheme = $dsn->getScheme();

        if ('zendesk' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'zendesk', $this->getSupportedSchemes());
        }

        $emailAddress = $this->getUser($dsn);
        $apiToken = $this->getPassword($dsn);
        $host = $this->getHost($dsn);

        return (new ZendeskTransport($emailAddress, $apiToken, $this->client, $this->dispatcher))->setHost($host);
    }

    protected function getSupportedSchemes(): array
    {
        return ['zendesk'];
    }

    private function getHost(Dsn $dsn): string
    {
        $host = $dsn->getHost();
        if ('default' === $host) {
            throw new IncompleteDsnException('Host is not set.', 'zendesk://default');
        }

        if (!str_ends_with($host, '.zendesk.com')) {
            throw new IncompleteDsnException('Host must be in format: "subdomain.zendesk.com".', 'zendesk://'.$dsn->getHost());
        }

        return $host;
    }
}
