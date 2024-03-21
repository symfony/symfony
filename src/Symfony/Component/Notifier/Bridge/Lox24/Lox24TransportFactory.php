<?php

declare(strict_types=1);

namespace Symfony\Component\Notifier\Bridge\Lox24;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Andrei Lebedev <andrew.lebedev@gmail.com>
 */
final class Lox24TransportFactory extends AbstractTransportFactory
{

    public function create(Dsn $dsn): Lox24Transport
    {
        $scheme = $dsn->getScheme();

        if (!in_array($scheme, $this->getSupportedSchemes(), true)) {
            throw new UnsupportedSchemeException($dsn, $scheme, $this->getSupportedSchemes());
        }

        $authUser = $this->getUser($dsn);
        $authToken = $this->getPassword($dsn);
        $auth = sprintf('%s:%s', $authUser, $authToken);
        $from = $dsn->getRequiredOption('from');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new Lox24Transport($auth, $from, $dsn->getOptions(), $this->client, $this->dispatcher))
            ->setHost($host)
            ->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['lox24'];
    }
}