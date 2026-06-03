<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Iqsms;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Oleksandr Barabolia <alexandrbarabolya@gmail.com>
 */
final class IqsmsTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): IqsmsTransport
    {
        $scheme = $dsn->getScheme();

        if ('iqsms' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'iqsms', $this->getSupportedSchemes());
        }

        $login = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $from = $dsn->getRequiredOption('from');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new IqsmsTransport($login, $password, $from, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['iqsms'];
    }
}
