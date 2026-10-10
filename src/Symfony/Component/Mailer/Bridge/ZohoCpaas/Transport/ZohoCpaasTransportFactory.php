<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\ZohoCpaas\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Dennis Petersmann <dennis@petersmann.dev>
 */
final class ZohoCpaasTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();

        if ('zohocpaas' === $scheme || 'zohocpaas+api' === $scheme) {
            return (new ZohoCpaasApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))
                ->setHost($host)
                ->setPort($dsn->getPort());
        }

        if ('zohocpaas+smtp' === $scheme || 'zohocpaas+smtps' === $scheme) {
            return new ZohoCpaasSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), $host, $dsn->getPort(), 'zohocpaas+smtps' === $scheme ?: null, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'zohocpaas', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['zohocpaas', 'zohocpaas+api', 'zohocpaas+smtp', 'zohocpaas+smtps'];
    }
}
