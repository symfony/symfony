<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GatewayApi;

use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Piergiuseppe Longo <piergiuseppe.longo@gmail.com>
 *
 * @experimental in 5.3
 */
final class GatewayApiTransportFactory extends AbstractTransportFactory
{
    /**
     * @return GatewayApiTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('gatewayapi' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'gatewayapi', $this->getSupportedSchemes());
        }

        $authToken = $dsn->getUser();
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $from = $dsn->getOption('from');
        $port = $dsn->getPort();

        if (!$from) {
            throw new IncompleteDsnException('Missing from.', $dsn->getOriginalDsn());
        }

        if (!$authToken) {
            throw new IncompleteDsnException('Missing auth token.', $dsn->getOriginalDsn());
        }

        if ('gatewayapi' === $scheme) {
            return (new GatewayApiTransport($authToken, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
        }

        throw new UnsupportedSchemeException($dsn, 'gatewayapi', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['gatewayapi'];
    }
}
