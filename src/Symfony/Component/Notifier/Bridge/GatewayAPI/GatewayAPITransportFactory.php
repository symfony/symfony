<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GatewayAPI;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Piergiuseppe Longo <piergiuseppe.longo@gmail.com>
 * @experimental in 5.2
 */
class GatewayAPITransportFactory extends AbstractTransportFactory
{
    /**
     * @return GatewayAPITransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $authToken = $dsn->getUser();
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $from = $dsn->getOption('from');
        $port = $dsn->getPort();

        if ('gatewaypi' === $scheme) {
            return (new GatewayAPITransport($authToken, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
        }

        throw new UnsupportedSchemeException($dsn, 'gatewaypi', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['gatewaypi'];
    }
}
