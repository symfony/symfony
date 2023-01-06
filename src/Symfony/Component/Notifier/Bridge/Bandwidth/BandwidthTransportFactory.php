<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Bandwidth;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author gnito-org <https://github.com/gnito-org>
 */
final class BandwidthTransportFactory extends AbstractTransportFactory
{
    private const TRANSPORT_SCHEME = 'bandwidth';

    public function create(Dsn $dsn): BandwidthTransport
    {
        $scheme = $dsn->getScheme();

        if (self::TRANSPORT_SCHEME !== $scheme) {
            throw new UnsupportedSchemeException($dsn, self::TRANSPORT_SCHEME, $this->getSupportedSchemes());
        }

        $username = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $from = $dsn->getRequiredOption('from');
        $accountId = $dsn->getRequiredOption('account_id');
        $applicationId = $dsn->getRequiredOption('application_id');
        $priority = $dsn->getOption('priority');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new BandwidthTransport($username, $password, $from, $accountId, $applicationId, $priority, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return [self::TRANSPORT_SCHEME];
    }
}
