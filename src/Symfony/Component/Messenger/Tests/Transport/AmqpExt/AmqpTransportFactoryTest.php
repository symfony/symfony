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
use Symfony\Component\Messenger\Transport\Dsn;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class AmqpTransportFactoryTest extends TestCase
{
    /**
     * @dataProvider supportsProvider
     */
    public function testSupports(Dsn $dsn, bool $supports): void
    {
        $factory = new AmqpTransportFactory();

        $this->assertSame($supports, $factory->supports($dsn));
    }

    public function supportsProvider(): iterable
    {
        yield [Dsn::fromString('amqp://localhost'), true];

        yield [Dsn::fromString('foo://localhost'), false];
    }

    public function testCreate(): void
    {
        $factory = new AmqpTransportFactory();
        $serializer = $this->createMock(SerializerInterface::class);

        $dsn = Dsn::fromString('amqp://localhost', ['foo' => 'bar']);
        $expectedTransport = new AmqpTransport(Connection::fromDsnObject($dsn), $serializer);

        $this->assertEquals($expectedTransport, $factory->createTransport($dsn, $serializer, 'amqp'));
    }
}
