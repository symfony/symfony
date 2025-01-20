<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postal\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class PostalTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if (!\in_array($scheme, $this->getSupportedSchemes(), true)) {
            throw new UnsupportedSchemeException($dsn, 'postal', $this->getSupportedSchemes());
        }

        $host = $dsn->getHost();
        $port = $dsn->getPort();
        $apiToken = $this->getPassword($dsn);

        return (new PostalApiTransport($apiToken, $host, $this->client, $this->dispatcher, $this->logger))->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['postal', 'postal+api'];
    }
}
