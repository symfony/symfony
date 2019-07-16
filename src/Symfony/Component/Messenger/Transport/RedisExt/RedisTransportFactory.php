<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\RedisExt;

use Symfony\Component\Messenger\Transport\Dsn;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Alexander Schranz <alexander@suluio>
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
class RedisTransportFactory implements TransportFactoryInterface
{
    public function createTransport(Dsn $dsn, SerializerInterface $serializer, string $name): TransportInterface
    {
        return new RedisTransport(Connection::fromDsnObject($dsn), $serializer);
    }

    public function supports(Dsn $dsn): bool
    {
        return 'redis' === $dsn->getScheme();
    }
}
