<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\MongoDb\Transport;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;

/**
 * @author Alessandro Lai <alessandro.lai85@gmail.com>
 *
 * @implements TransportFactoryInterface<MongoDbTransport>
 */
class MongoDbTransportFactory implements TransportFactoryInterface
{
    public function __construct(
        private readonly ClockInterface $clock = new Clock(),
    ) {
    }

    public function createTransport(#[\SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): MongoDbTransport
    {
        unset($options['transport_name']);

        return new MongoDbTransport(Connection::fromDsn($dsn, $options, null, $this->clock), $serializer);
    }

    public function supports(#[\SensitiveParameter] string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'mongodb://') || str_starts_with($dsn, 'mongodb+srv://');
    }
}
