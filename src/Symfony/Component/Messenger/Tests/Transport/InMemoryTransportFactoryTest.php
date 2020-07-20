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

    public function testResetCreatedTransports()
    {
        $transport = $this->factory->createTransport('in-memory://', [], $this->createMock(SerializerInterface::class));
        $transport->send(Envelope::wrap(new DummyMessage('Hello.')));

        $this->assertCount(1, $transport->get());
        $this->factory->reset();
        $this->assertCount(0, $transport->get());
    }

    public function provideDSN(): array
    {
        return [
            'Supported' => ['in-memory://foo'],
            'Unsupported' => ['amqp://bar', false],
        ];
    }
}
