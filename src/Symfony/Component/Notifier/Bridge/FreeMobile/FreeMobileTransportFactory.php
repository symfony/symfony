<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FreeMobile;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Antoine Makdessi <amakdessi@me.com>
 */
final class FreeMobileTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): FreeMobileTransport
    {
        $scheme = $dsn->getScheme();

        if ('freemobile' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'freemobile', $this->getSupportedSchemes());
        }

        $login = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $phone = $dsn->getRequiredOption('phone');

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new FreeMobileTransport($login, $password, $phone, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['freemobile'];
    }
}
