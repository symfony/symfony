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

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class AmqpTransportFactory implements TransportFactoryInterface
{
    private $serializer;
    private $debug;

    public function __construct(SerializerInterface $serializer, bool $debug)
    {
        $this->serializer = $serializer;
        $this->debug = $debug;
    }

    public function createTransport(string $dsn, array $options): TransportInterface
    {
        return new AmqpTransport($this->serializer, Connection::fromDsn($dsn, $options, $this->debug));
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === strpos($dsn, 'amqp://');
    }
}
