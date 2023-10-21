<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Expo;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Imad ZAIRIG <https://github.com/zairigimad>
 */
final class ExpoTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): ExpoTransport
    {
        $scheme = $dsn->getScheme();

        if ('expo' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'expo', $this->getSupportedSchemes());
        }

        $token = $dsn->getUser($dsn);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new ExpoTransport($token, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['expo'];
    }
}
