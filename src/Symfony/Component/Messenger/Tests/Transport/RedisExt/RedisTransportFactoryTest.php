<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\RedisExt;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\RedisExt\Connection;
use Symfony\Component\Messenger\Transport\RedisExt\RedisTransport;
use Symfony\Component\Messenger\Transport\RedisExt\RedisTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class RedisTransportFactoryTest extends TestCase
{
    public function testSupportsOnlyRedisTransports()
    {
        $factory = new RedisTransportFactory(
            $this->getMockBuilder(SerializerInterface::class)->getMock()
        );

        $this->assertTrue($factory->supports('redis://localhost', array()));
        $this->assertFalse($factory->supports('sqs://localhost', array()));
        $this->assertFalse($factory->supports('invalid-dsn', array()));
    }

    public function testItCreatesTheTransport()
    {
        $factory = new RedisTransportFactory(
            $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock()
        );

        $expectedTransport = new RedisTransport(Connection::fromDsn('redis://localhost', array('foo' => 'bar'), true), $serializer);

        $this->assertEquals($expectedTransport, $factory->createTransport('redis://localhost', array('foo' => 'bar')));
    }
}
