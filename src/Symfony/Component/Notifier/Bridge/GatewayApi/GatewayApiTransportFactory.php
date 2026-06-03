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

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Piergiuseppe Longo <piergiuseppe.longo@gmail.com>
 */
final class GatewayApiTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): GatewayApiTransport
    {
        $scheme = $dsn->getScheme();

        if ('gatewayapi' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'gatewayapi', $this->getSupportedSchemes());
        }

        $authToken = $this->getUser($dsn);
        $from = $dsn->getRequiredOption('from');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new GatewayApiTransport($authToken, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['gatewayapi'];
    }
}
