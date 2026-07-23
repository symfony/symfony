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
    private InMemoryTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new InMemoryTransport();
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
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer
            ->method('encode')
            ->willReturnCallback(function (Envelope $encodedEnvelope) use ($envelope) {
                $this->assertEquals($envelope->with(new TransportMessageIdStamp(1)), $encodedEnvelope);

                return ['foo' => 'ba'];
            })
        ;
        $serializer
            ->method('decode')
            ->willReturnMap([
                [['foo' => 'ba'], $envelopeDecoded],
            ])
        ;
        $serializeTransport = new InMemoryTransport($serializer);
        $serializeTransport->send($envelope);
        $this->assertSame([$envelopeDecoded], $serializeTransport->getSent());
    }

    public function testQueue()
    {
        $envelope1 = new Envelope(new \stdClass());
        $envelope1 = $this->transport->send($envelope1);
        $envelope2 = new Envelope(new \stdClass());
        $envelope2 = $this->transport->send($envelope2);
        $this->assertSame([$envelope1, $envelope2], $this->transport->get(2));
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
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer
            ->method('encode')
            ->willReturnCallback(function (Envelope $encodedEnvelope) use ($envelope) {
                $this->assertEquals($envelope->with(new TransportMessageIdStamp(1)), $encodedEnvelope);

                return ['foo' => 'ba'];
            })
        ;
        $serializer
            ->method('decode')
            ->willReturnMap([
                [['foo' => 'ba'], $envelopeDecoded],
            ])
        ;
        $serializeTransport = new InMemoryTransport($serializer);
        $serializeTransport->send($envelope);
        $this->assertSame([$envelopeDecoded], $serializeTransport->get());
    }

    public function testGetUsesFetchSizeWhenProvided()
    {
        $envelope1 = $this->transport->send(new Envelope(new \stdClass()));
        $envelope2 = $this->transport->send(new Envelope(new \stdClass()));

        $this->assertSame([$envelope1], $this->transport->get(1));
        $this->assertSame([$envelope1, $envelope2], $this->transport->get(2));
    }

    public function testAcknowledgeSameMessageWithDifferentStamps()
    {
        $envelope1 = new Envelope(new \stdClass(), [new AnEnvelopeStamp()]);
        $envelope1 = $this->transport->send($envelope1);
        $envelope2 = new Envelope(new \stdClass(), [new AnEnvelopeStamp()]);
        $envelope2 = $this->transport->send($envelope2);
        $this->assertSame([$envelope1, $envelope2], $this->transport->get(2));
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
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer
            ->method('encode')
            ->willReturnCallback(function (Envelope $encodedEnvelope) use ($envelope) {
                $this->assertEquals($envelope->with(new TransportMessageIdStamp(1)), $encodedEnvelope);

                return ['foo' => 'ba'];
            })
        ;
        $serializer
            ->method('decode')
            ->willReturnMap([
                [['foo' => 'ba'], $envelopeDecoded],
            ])
        ;
        $serializeTransport = new InMemoryTransport($serializer);
        $serializeTransport->ack($envelope->with(new TransportMessageIdStamp(1)));
        $this->assertSame([$envelopeDecoded], $serializeTransport->getAcknowledged());
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
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer
            ->method('encode')
            ->willReturnCallback(function (Envelope $encodedEnvelope) use ($envelope) {
                $this->assertEquals($envelope->with(new TransportMessageIdStamp(1)), $encodedEnvelope);

                return ['foo' => 'ba'];
            })
        ;
        $serializer
            ->method('decode')
            ->willReturnMap([
                [['foo' => 'ba'], $envelopeDecoded],
            ])
        ;
        $serializeTransport = new InMemoryTransport($serializer);
        $serializeTransport->reject($envelope->with(new TransportMessageIdStamp(1)));
        $this->assertSame([$envelopeDecoded], $serializeTransport->getRejected());
    }

    public function testReset()
    {
        $envelope = new Envelope(new \stdClass());
        $envelope = $this->transport->send($envelope);
        $this->transport->ack($envelope);
        $this->transport->reject($envelope);

        $this->transport->reset();

        $this->assertSame([], $this->transport->get(), 'Should be empty after reset');
        $this->assertSame([], $this->transport->getAcknowledged(), 'Should be empty after reset');
        $this->assertSame([], $this->transport->getRejected(), 'Should be empty after reset');
        $this->assertSame([], $this->transport->getSent(), 'Should be empty after reset');
    }
}
