<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Prelude;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Imad <imadzairig@gmail.com>
 */
final class PreludeTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): PreludeTransport
    {
        $scheme = $dsn->getScheme();

        if ('prelude' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'prelude', $this->getSupportedSchemes());
        }

        $apiKey = $this->getUser($dsn);
        $sender = $dsn->getOption('sender'); // Sender might be optional if configured in Prelude dashboard
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new PreludeTransport($apiKey, $sender ?? '', $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['prelude'];
    }
}
