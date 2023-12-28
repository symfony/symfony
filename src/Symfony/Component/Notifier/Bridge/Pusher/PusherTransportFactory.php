<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Pusher;

use Pusher\Pusher;
use Symfony\Component\Notifier\Exception\MissingRequiredOptionException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Yasmany Cubela Medina <yasmanycm@gmail.com>
 */
final class PusherTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if ('pusher' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'pusher', $this->getSupportedSchemes());
        }

        if (null === $dsn->getUser() || null === $dsn->getPassword()) {
            throw new MissingRequiredOptionException('Pusher needs APP_KEY and APP_SECRET specified.');
        }

        return new PusherTransport(
            new Pusher($dsn->getUser(), $dsn->getPassword(), $dsn->getHost(), ['cluster' => $dsn->getRequiredOption('server'),]),
            $this->client,
            $this->dispatcher
        );
    }

    protected function getSupportedSchemes(): array
    {
        return ['pusher'];
    }
}
