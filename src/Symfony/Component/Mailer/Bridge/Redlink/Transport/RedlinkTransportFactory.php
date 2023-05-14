<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Redlink\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Mateusz Żyła <https://github.com/plotkabytes>
 */
final class RedlinkTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if (!\in_array($dsn->getScheme(), $this->getSupportedSchemes(), true)) {
            throw new UnsupportedSchemeException($dsn, 'redlink', $this->getSupportedSchemes());
        }

        return (new RedlinkApiTransport(
            $this->getUser($dsn),
            $this->getPassword($dsn),
            $dsn->getOption('fromSmtp'),
            $dsn->getOption('version'),
            $this->client,
            $this->dispatcher,
            $this->logger
        )
        )
            ->setHost('default' === $dsn->getHost() ? null : $dsn->getHost())
            ->setPort($dsn->getPort());
    }

    protected function getSupportedSchemes(): array
    {
        return ['redlink', 'redlink+api'];
    }
}
