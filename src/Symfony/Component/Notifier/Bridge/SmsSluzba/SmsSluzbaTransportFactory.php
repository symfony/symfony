<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsSluzba;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Dennis Fridrich <fridrich.dennis@gmail.com>
 */
final class SmsSluzbaTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): SmsSluzbaTransport
    {
        $scheme = $dsn->getScheme();

        if ('sms-sluzba' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'sms-sluzba', $this->getSupportedSchemes());
        }

        $username = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new SmsSluzbaTransport($username, $password, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['sms-sluzba'];
    }
}
