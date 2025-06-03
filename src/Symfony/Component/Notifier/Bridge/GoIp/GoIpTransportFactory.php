<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GoIp;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Ahmed Ghanem <ahmedghanem7361@gmail.com>
 */
final class GoIpTransportFactory extends AbstractTransportFactory
{
    private const SCHEME_NAME = 'goip';

    public function create(Dsn $dsn): GoIpTransport
    {
        if (self::SCHEME_NAME !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, self::SCHEME_NAME, $this->getSupportedSchemes());
        }

        $username = $this->getUser($dsn);
        $password = $this->getPassword($dsn);

        if (0 === ($simSlot = (int) $dsn->getRequiredOption('sim_slot'))) {
            throw new InvalidArgumentException(\sprintf('The provided SIM-Slot: "%s" is not valid.', $simSlot));
        }

        return (new GoIpTransport($username, $password, $simSlot, $this->client, $this->dispatcher))
            ->setHost($dsn->getHost())
            ->setPort($dsn->getPort());
    }

    protected function getSupportedSchemes(): array
    {
        return [
            self::SCHEME_NAME,
        ];
    }
}
