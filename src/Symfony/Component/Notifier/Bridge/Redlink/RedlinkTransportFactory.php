<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Redlink;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Mateusz Żyła <https://github.com/plotkabytes>
 */
final class RedlinkTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if ('redlink' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'redlink', $this->getSupportedSchemes());
        }

        $apiKey = $dsn->getUser();
        $appToken = $dsn->getPassword();
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        $from = $dsn->getRequiredOption('from');
        $version = $dsn->getRequiredOption('version');

        return (new RedlinkTransport($apiKey, $appToken, $from, $version, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['redlink'];
    }
}
