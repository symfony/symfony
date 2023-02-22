<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\InMemory;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransportFactory;
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
     * @dataProvider provideDSN
     */
    public function testSupports(string $dsn, bool $expected = true)
    {
        $this->assertSame($expected, $this->factory->supports($dsn, []), 'InMemoryTransportFactory::supports returned unexpected result.');
    }

    public function testCreateTransport()
    {
        /** @var SerializerInterface $serializer */
        $serializer = $this->createMock(SerializerInterface::class);

        $this->assertInstanceOf(InMemoryTransport::class, $this->factory->createTransport('in-memory://', [], $serializer));
    }

    public function testCreateTransportWithoutSerializer()
    {
        /** @var SerializerInterface $serializer */
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer
            ->expects($this->never())
            ->method('encode')
        ;
        $transport = $this->factory->createTransport('in-memory://?serialize=false', [], $serializer);
        $message = Envelope::wrap(new DummyMessage('Hello.'));
        $transport->send($message);

        $this->assertEquals([$message->with(new TransportMessageIdStamp(1))], $transport->get());
    }

    public function testCreateTransportWithSerializer()
    {
        /** @var SerializerInterface $serializer */
        $serializer = $this->createMock(SerializerInterface::class);
        $message = Envelope::wrap(new DummyMessage('Hello.'));
        $serializer
            ->expects($this->once())
            ->method('encode')
            ->with($this->equalTo($message->with(new TransportMessageIdStamp(1))))
        ;
        $transport = $this->factory->createTransport('in-memory://?serialize=true', [], $serializer);
        $transport->send($message);
    }

    public function testResetCreatedTransports()
    {
        $transport = $this->factory->createTransport('in-memory://', [], $this->createMock(SerializerInterface::class));
        $transport->send(Envelope::wrap(new DummyMessage('Hello.')));

        $this->assertCount(1, $transport->get());
        $this->factory->reset();
        $this->assertCount(0, $transport->get());
    }

    public static function provideDSN(): array
    {
        return [
            'Supported' => ['in-memory://foo'],
            'Serialize enabled' => ['in-memory://?serialize=true'],
            'Serialize disabled' => ['in-memory://?serialize=false'],
            'Unsupported' => ['amqp://bar', false],
        ];
    }
}
