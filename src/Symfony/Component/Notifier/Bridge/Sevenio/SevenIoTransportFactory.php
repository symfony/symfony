<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sevenio;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Frank Nägler <frank@naegler.hamburg>
 */
final class SevenIoTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): SevenIoTransport
    {
        $scheme = $dsn->getScheme();

        if ('sevenio' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'sevenio', $this->getSupportedSchemes());
        }

        $apiKey = $this->getUser($dsn);
        $from = $dsn->getOption('from');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new SevenIoTransport($apiKey, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['sevenio'];
    }
}
