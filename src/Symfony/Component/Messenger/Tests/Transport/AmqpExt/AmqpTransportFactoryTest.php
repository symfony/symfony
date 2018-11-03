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
        $factory = new AmqpTransportFactory(
            $this->getMockBuilder(SerializerInterface::class)->getMock(),
            true
        );

        $this->assertTrue($factory->supports('amqp://localhost', array()));
        $this->assertFalse($factory->supports('sqs://localhost', array()));
        $this->assertFalse($factory->supports('invalid-dsn', array()));
    }

    public function testItCreatesTheTransport()
    {
        $factory = new AmqpTransportFactory(
            $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock(),
            true
        );

        $expectedTransport = new AmqpTransport(Connection::fromDsn('amqp://localhost', array('foo' => 'bar'), true), $serializer);

        $this->assertEquals($expectedTransport, $factory->createTransport('amqp://localhost', array('foo' => 'bar')));
    }
}
