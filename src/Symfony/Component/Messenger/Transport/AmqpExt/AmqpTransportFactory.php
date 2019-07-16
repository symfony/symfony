<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\AmqpExt;

use Symfony\Component\Messenger\Transport\Dsn;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class AmqpTransportFactory implements TransportFactoryInterface
{
    public function createTransport(Dsn $dsn, SerializerInterface $serializer, string $name): TransportInterface
    {
        return new AmqpTransport(Connection::fromDsnObject($dsn), $serializer);
    }

    public function supports(Dsn $dsn): bool
    {
        return 'amqp' === $dsn->getScheme();
    }
}
