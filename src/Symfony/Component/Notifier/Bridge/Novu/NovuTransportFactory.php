<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Novu;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Wouter van der Loop <woutervdl@toppy.nl>
 */
class NovuTransportFactory extends AbstractTransportFactory
{
    private const SCHEME = 'novu';

    protected function getSupportedSchemes(): array
    {
        return [self::SCHEME];
    }

    public function create(Dsn $dsn): NovuTransport
    {
        $scheme = $dsn->getScheme();
        if (self::SCHEME !== $scheme) {
            throw new UnsupportedSchemeException($dsn, self::SCHEME, $this->getSupportedSchemes());
        }

        $key = $this->getUser($dsn);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new NovuTransport($key, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }
}
