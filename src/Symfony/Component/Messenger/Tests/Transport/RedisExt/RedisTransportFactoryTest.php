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
use Symfony\Component\Messenger\Transport\Dsn;
use Symfony\Component\Messenger\Transport\RedisExt\Connection;
use Symfony\Component\Messenger\Transport\RedisExt\RedisTransport;
use Symfony\Component\Messenger\Transport\RedisExt\RedisTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @requires extension redis
 */
class RedisTransportFactoryTest extends TestCase
{
    /**
     * @dataProvider supportsProvider
     */
    public function testSupports(Dsn $dsn, bool $supports): void
    {
        $factory = new RedisTransportFactory();

        $this->assertSame($supports, $factory->supports($dsn));
    }

    public function supportsProvider(): iterable
    {
        yield [Dsn::fromString('redis://localhost'), true];

        yield [Dsn::fromString('foo://localhost'), false];
    }

    public function testCreate(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $dsn = Dsn::fromString('redis://localhost', ['foo' => 'bar']);
        $expectedTransport = new RedisTransport(Connection::fromDsnObject($dsn), $serializer);
        $factory = new RedisTransportFactory();

        $this->assertEquals($expectedTransport, $factory->createTransport($dsn, $serializer, 'redis'));
    }
}
