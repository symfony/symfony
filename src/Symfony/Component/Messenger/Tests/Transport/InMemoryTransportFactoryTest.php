<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Dsn;
use Symfony\Component\Messenger\Transport\InMemoryTransport;
use Symfony\Component\Messenger\Transport\InMemoryTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class InMemoryTransportFactoryTest extends TestCase
{
    /**
     * @var InMemoryTransportFactory
     */
    private $factory;

    protected function setUp(): void
    {
        $this->factory = new InMemoryTransportFactory();
    }

    /**
     * @dataProvider supportsProvider
     */
    public function testSupports(Dsn $dsn, bool $supports): void
    {
        $this->assertSame($supports, $this->factory->supports($dsn));
    }

    public function supportsProvider(): iterable
    {
        yield [Dsn::fromString('in-memory://'), true];

        yield [Dsn::fromString('foo://localhost'), false];
    }

    public function testCreate(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $dsn = Dsn::fromString('in-memory://');
        $expectedTransport = new InMemoryTransport();

        $this->assertEquals($expectedTransport, $this->factory->createTransport($dsn, $serializer, 'in-memory'));
    }

    public function testResetCreatedTransports(): void
    {
        $dsn = Dsn::fromString('in-memory://');
        $transport = $this->factory->createTransport($dsn, $this->createMock(SerializerInterface::class), 'in-memory');
        $transport->send(Envelope::wrap(new DummyMessage('Hello.')));

        $this->assertCount(1, $transport->get());
        $this->factory->reset();
        $this->assertCount(0, $transport->get());
    }
}
