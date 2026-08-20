<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Cloudflare\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author vadage
 */
final class CloudflareTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('cloudflare+api' === $scheme || 'cloudflare' === $scheme) {
            $accountId = $this->getUser($dsn);
            $apiToken = $this->getPassword($dsn);
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();

            return new CloudflareApiTransport($accountId, $apiToken, $this->client, $this->dispatcher, $this->logger)
                ->setHost($host)
                ->setPort($port);
        }

        if ('cloudflare+smtp' === $scheme) {
            return new CloudflareSmtpTransport($this->getPassword($dsn), $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'cloudflare', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['cloudflare', 'cloudflare+api', 'cloudflare+smtp'];
    }
}
