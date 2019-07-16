<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Sync;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Dsn;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class SyncTransportFactory implements TransportFactoryInterface
{
    private $messageBus;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    public function createTransport(Dsn $dsn, SerializerInterface $serializer, string $name): TransportInterface
    {
        return new SyncTransport($this->messageBus);
    }

    public function supports(Dsn $dsn): bool
    {
        return 'sync' === $dsn->getScheme();
    }
}
