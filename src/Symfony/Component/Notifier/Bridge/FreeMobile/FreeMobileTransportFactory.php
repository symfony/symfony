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

use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Antoine Makdessi <amakdessi@me.com>
 *
 * @experimental in 5.1
 */
final class FreeMobileTransportFactory extends AbstractTransportFactory
{
    /**
     * @return FreeMobileTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $login = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $phone = $dsn->getOption('phone');

        if (!$phone) {
            throw new IncompleteDsnException('Missing phone.', $dsn->getOriginalDsn());
        }

        if ('freemobile' === $scheme) {
            return new FreeMobileTransport($login, $password, $phone, $this->client, $this->dispatcher);
        }

        throw new UnsupportedSchemeException($dsn, 'freemobile', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['freemobile'];
    }
}
