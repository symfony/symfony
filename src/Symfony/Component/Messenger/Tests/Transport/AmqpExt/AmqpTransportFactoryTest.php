<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\AmqpExt;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpTransport;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpTransportFactory;
use Symfony\Component\Messenger\Transport\AmqpExt\Connection;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class AmqpTransportFactoryTest extends TestCase
{
    public function testSupportsOnlyAmqpTransports()
    {
        $factory = new AmqpTransportFactory();

        $this->assertTrue($factory->supports('amqp://localhost', []));
        $this->assertFalse($factory->supports('sqs://localhost', []));
        $this->assertFalse($factory->supports('invalid-dsn', []));
    }

    public function testItCreatesTheTransport()
    {
        $factory = new AmqpTransportFactory();
        $serializer = $this->createMock(SerializerInterface::class);

        $expectedTransport = new AmqpTransport(Connection::fromDsn('amqp://localhost', ['foo' => 'bar']), $serializer);

        $this->assertEquals($expectedTransport, $factory->createTransport('amqp://localhost', ['foo' => 'bar'], $serializer));
    }
}
