<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\AllMySms;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Quentin Dequippe <quentin@dequippe.tech>
 */
final class AllMySmsTransportFactory extends AbstractTransportFactory
{
    /**
     * @return AllMySmsTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('allmysms' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'allmysms', $this->getSupportedSchemes());
        }

        $login = $this->getUser($dsn);
        $apiKey = $this->getPassword($dsn);
        $from = $dsn->getOption('from');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new AllMySmsTransport($login, $apiKey, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['allmysms'];
    }
}
