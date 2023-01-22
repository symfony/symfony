<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Redis\Transport;

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Alexander Schranz <alexander@suluio>
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
class RedisTransportFactory implements TransportFactoryInterface
{
    public function createTransport(#[\SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        unset($options['transport_name']);

        return new RedisTransport(Connection::fromDsn($dsn, $options), $serializer);
    }

    public function supports(#[\SensitiveParameter] string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'redis://') || str_starts_with($dsn, 'rediss://');
    }
}
