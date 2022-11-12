<?php

declare(strict_types=1);

namespace Symfony\Component\Notifier\Bridge\Pusher;

use Pusher\Pusher;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Notifier\Exception\MissingRequiredOptionException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Yasmany Cubela Medina <yasmanycm@gmail.com>
 */
#[Autoconfigure(tags: ['texter.transport_factory'])]
final class PusherTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if ('pusher' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'pusher', $this->getSupportedSchemes());
        }

        if (null === $dsn->getUser() || null === $dsn->getPassword() || null === $dsn->getOption('server')) {
            throw new MissingRequiredOptionException('Pusher needs a APP_KEY, APP_SECRET AND SERVER specified.');
        }

        $options = [
            'cluster' => $dsn->getOption('server', 'mt1'),
        ];

        $pusherClient = new Pusher($dsn->getUser(), $dsn->getPassword(), $dsn->getHost(), $options);

        return new PusherTransport($pusherClient, $this->client, $this->dispatcher);
    }

    protected function getSupportedSchemes(): array
    {
        return ['pusher'];
    }
}
