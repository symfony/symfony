<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\PufferPost\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Jeroen Moonen (PufferPost) <info@jeroenmoonen.nl>
 */
final class PufferPostTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('pufferpost' === $scheme || 'pufferpost+api' === $scheme) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();

            return (new PufferPostApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))
                ->setHost($host)
                ->setPort($dsn->getPort());
        }

        throw new UnsupportedSchemeException($dsn, 'pufferpost', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['pufferpost', 'pufferpost+api'];
    }
}
