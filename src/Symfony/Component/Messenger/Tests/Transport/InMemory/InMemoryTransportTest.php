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
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Tests\Fixtures\AnEnvelopeStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class InMemoryTransportTest extends TestCase
{
    /**
     * @var InMemoryTransport
     */
    private $transport;

    /**
     * @var InMemoryTransport
     */
    private $serializeTransport;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->transport = new InMemoryTransport();
        $this->serializeTransport = new InMemoryTransport($this->serializer);
    }

    public function testSend()
    {
        $envelope = new Envelope(new \stdClass());
        $this->transport->send($envelope);
        $this->assertEquals([$envelope->with(new TransportMessageIdStamp(1))], $this->transport->getSent());
    }

    public function testSendWithSerialization()
    {
        $envelope = new Envelope(new \stdClass());
        $envelopeDecoded = Envelope::wrap(new DummyMessage('Hello.'));
        $this->serializer
            ->method('encode')
            ->with($this->equalTo($envelope->with(new TransportMessageIdStamp(1))))
            ->willReturn(['foo' => 'ba'])
        ;
        $this->serializer
            ->method('decode')
            ->with(['foo' => 'ba'])
            ->willReturn($envelopeDecoded)
        ;
        $this->serializeTransport->send($envelope);
        $this->assertSame([$envelopeDecoded], $this->serializeTransport->getSent());
    }

    public function testQueue()
    {
        $envelope1 = new Envelope(new \stdClass());
        $envelope1 = $this->transport->send($envelope1);
        $envelope2 = new Envelope(new \stdClass());
        $envelope2 = $this->transport->send($envelope2);
        $this->assertSame([$envelope1, $envelope2], $this->transport->get());
        $this->transport->ack($envelope1);
        $this->assertSame([$envelope2], $this->transport->get());
        $this->transport->reject($envelope2);
        $this->assertSame([], $this->transport->get());
    }

    public function testQueueWithDelay()
    {
        $envelope1 = new Envelope(new \stdClass());
        $envelope1 = $this->transport->send($envelope1);
        $envelope2 = (new Envelope(new \stdClass()))->with(new DelayStamp(10_000));
        $envelope2 = $this->transport->send($envelope2);
        $this->assertSame([$envelope1], $this->transport->get());
    }

    public function testQueueWithSerialization()
    {
        $envelope = new Envelope(new \stdClass());
        $envelopeDecoded = Envelope::wrap(new DummyMessage('Hello.'));
        $this->serializer
            ->method('encode')
            ->with($this->equalTo($envelope->with(new TransportMessageIdStamp(1))))
            ->willReturn(['foo' => 'ba'])
        ;
        $this->serializer
            ->method('decode')
            ->with(['foo' => 'ba'])
            ->willReturn($envelopeDecoded)
        ;
        $this->serializeTransport->send($envelope);
        $this->assertSame([$envelopeDecoded], $this->serializeTransport->get());
    }

    public function testAcknowledgeSameMessageWithDifferentStamps()
    {
        $envelope1 = new Envelope(new \stdClass(), [new AnEnvelopeStamp()]);
        $envelope1 = $this->transport->send($envelope1);
        $envelope2 = new Envelope(new \stdClass(), [new AnEnvelopeStamp()]);
        $envelope2 = $this->transport->send($envelope2);
        $this->assertSame([$envelope1, $envelope2], $this->transport->get());
        $this->transport->ack($envelope1->with(new AnEnvelopeStamp()));
        $this->assertSame([$envelope2], $this->transport->get());
        $this->transport->reject($envelope2->with(new AnEnvelopeStamp()));
        $this->assertSame([], $this->transport->get());
    }

    public function testAck()
    {
        $envelope = new Envelope(new \stdClass());
        $envelope = $this->transport->send($envelope);
        $this->transport->ack($envelope);
        $this->assertSame([$envelope], $this->transport->getAcknowledged());
    }

    public function testAckWithSerialization()
    {
        $envelope = new Envelope(new \stdClass());
        $envelopeDecoded = Envelope::wrap(new DummyMessage('Hello.'));
        $this->serializer
            ->method('encode')
            ->with($this->equalTo($envelope->with(new TransportMessageIdStamp(1))))
            ->willReturn(['foo' => 'ba'])
        ;
        $this->serializer
            ->method('decode')
            ->with(['foo' => 'ba'])
            ->willReturn($envelopeDecoded)
        ;
        $this->serializeTransport->ack($envelope->with(new TransportMessageIdStamp(1)));
        $this->assertSame([$envelopeDecoded], $this->serializeTransport->getAcknowledged());
    }

    public function testReject()
    {
        $envelope = new Envelope(new \stdClass());
        $envelope = $this->transport->send($envelope);
        $this->transport->reject($envelope);
        $this->assertSame([$envelope], $this->transport->getRejected());
    }

    public function testRejectWithSerialization()
    {
        $envelope = new Envelope(new \stdClass());
        $envelopeDecoded = Envelope::wrap(new DummyMessage('Hello.'));
        $this->serializer
            ->method('encode')
            ->with($this->equalTo($envelope->with(new TransportMessageIdStamp(1))))
            ->willReturn(['foo' => 'ba'])
        ;
        $this->serializer
            ->method('decode')
            ->with(['foo' => 'ba'])
            ->willReturn($envelopeDecoded)
        ;
        $this->serializeTransport->reject($envelope->with(new TransportMessageIdStamp(1)));
        $this->assertSame([$envelopeDecoded], $this->serializeTransport->getRejected());
    }

    public function testReset()
    {
        $envelope = new Envelope(new \stdClass());
        $envelope = $this->transport->send($envelope);
        $this->transport->ack($envelope);
        $this->transport->reject($envelope);

        $this->transport->reset();

        $this->assertEmpty($this->transport->get(), 'Should be empty after reset');
        $this->assertEmpty($this->transport->getAcknowledged(), 'Should be empty after reset');
        $this->assertEmpty($this->transport->getRejected(), 'Should be empty after reset');
        $this->assertEmpty($this->transport->getSent(), 'Should be empty after reset');
    }
}
