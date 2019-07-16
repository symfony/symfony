<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Sync;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Dsn;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\Sync\SyncTransportFactory;

class SyncTransportFactoryTest extends TestCase
{
    /**
     * @dataProvider supportsProvider
     */
    public function testSupports(Dsn $dsn, bool $supports): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $factory = new SyncTransportFactory($bus);

        $this->assertSame($supports, $factory->supports($dsn));
    }

    public function supportsProvider(): iterable
    {
        yield [Dsn::fromString('sync://'), true];

        yield [Dsn::fromString('foo://localhost'), false];
    }

    public function testCreate(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $bus = $this->createMock(MessageBusInterface::class);

        $dsn = Dsn::fromString('sync://');
        $expectedTransport = new SyncTransport($bus);
        $factory = new SyncTransportFactory($bus);

        $this->assertEquals($expectedTransport, $factory->createTransport($dsn, $serializer, 'sync'));
    }
}
