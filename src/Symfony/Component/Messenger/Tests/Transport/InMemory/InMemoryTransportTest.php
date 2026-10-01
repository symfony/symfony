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
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\DecodeFailedMessageMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedispatchStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Stamp\TrustStamp;
use Symfony\Component\Messenger\Tests\Fixtures\AnEnvelopeStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Worker;

/**
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class InMemoryTransportTest extends TestCase
{
    use ClockSensitiveTrait;

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

    public function testQueueWithSubSecondDelay()
    {
        $clock = new MockClock('2020-01-01 00:00:00');
        $transport = new InMemoryTransport(clock: $clock);
        $envelope = $transport->send((new Envelope(new \stdClass()))->with(new DelayStamp(500)));

        $clock->sleep(0.1);
        $this->assertSame([], $transport->get());

        $clock->sleep(0.5);
        $this->assertSame([$envelope], $transport->get());
    }

    public function testQueueWithDelayAcrossDstTransition()
    {
        // spring forward, fall back, and a day without a transition
        $this->assertDelayIsHonored('2026-03-28 12:00:00', '2026-03-29 12:00:00');
        $this->assertDelayIsHonored('2026-10-24 12:00:00', '2026-10-25 12:00:00');
        $this->assertDelayIsHonored('2026-06-01 12:00:00', '2026-06-02 12:00:00');
    }

    private function assertDelayIsHonored(string $now, string $target): void
    {
        $tz = new \DateTimeZone('Europe/Prague');
        $now = new \DateTimeImmutable($now, $tz);
        $target = new \DateTimeImmutable($target, $tz);

        // the delay is given in milliseconds rather than through DelayStamp::delayUntil(),
        // which reads the real clock on this branch
        $delay = ($target->getTimestamp() - $now->getTimestamp()) * 1000;

        $clock = self::mockTime($now);
        $transport = new InMemoryTransport(clock: $clock);
        $envelope = $transport->send((new Envelope(new \stdClass()))->with(new DelayStamp($delay)));

        $clock->sleep($delay / 1000 - 1);
        $this->assertSame([], $transport->get(), \sprintf('Message must not be available one second before %s.', $target->format('c')));

        $clock->sleep(2);
        $this->assertSame([$envelope], $transport->get(), \sprintf('Message must be available one second after %s.', $target->format('c')));
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
        $this->assertEquals([$envelopeDecoded->with(new TransportMessageIdStamp(1))], $serializeTransport->get());
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

    public function testAckAndRejectMessagesThatFailedToDecode()
    {
        $transport = new InMemoryTransport($this->createUndecodableSerializer());
        $transport->send(new Envelope(new DummyMessage('Hello.')));
        $transport->send(new Envelope(new DummyMessage('Hello.')));

        [$first, $second] = $transport->get(2);
        $transport->ack($first);
        $transport->reject($second);

        $this->assertSame([], $transport->get());
    }

    public function testWorkerSendsMessageThatFailedToDecodeToTheFailureTransport()
    {
        $serializer = $this->createUndecodableSerializer();
        $transport = new InMemoryTransport($serializer);
        $failureTransport = new InMemoryTransport();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['transport' => static fn () => $failureTransport])));
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        });
        $bus = new MessageBus([new DecodeFailedMessageMiddleware(new ServiceLocator(['transport' => static fn () => $serializer]))]);

        $transport->send(new Envelope(new DummyMessage('Hello.')));
        (new Worker(['transport' => $transport], $bus, $dispatcher))->run(['sleep' => 0]);

        $this->assertSame([], $transport->get());
        $this->assertCount(1, $failed = $failureTransport->getSent());
        $this->assertInstanceOf(MessageDecodingFailedException::class, $failed[0]->getMessage());
    }

    public function testARedispatchedMessageIsHandledWhenConsumedFromTheTransportItWasSentTo()
    {
        $calls = 0;
        $transport = $this->transport;
        $bus = new MessageBus([
            new SendMessageMiddleware(new SendersLocator([DummyMessage::class => ['transport']], new ServiceLocator(['transport' => static fn () => $transport]))),
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [static function () use (&$calls) { ++$calls; }]])),
        ]);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $bus->dispatch(new Envelope(new DummyMessage('Hello.'), [new ReceivedStamp('scheduler_default'), TrustStamp::trusted(), new RedispatchStamp()]));
        (new Worker(['transport' => $transport], $bus, $dispatcher))->run(['sleep' => 0]);

        $this->assertSame(1, $calls);
        $this->assertSame(0, $transport->getMessageCount());
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

    public function testGetMessageCount()
    {
        $this->assertSame(0, $this->transport->getMessageCount());

        $envelope1 = $this->transport->send(new Envelope(new \stdClass()));
        $envelope2 = $this->transport->send((new Envelope(new \stdClass()))->with(new DelayStamp(10_000)));
        $this->assertSame(2, $this->transport->getMessageCount());

        $this->transport->ack($envelope1);
        $this->assertSame(1, $this->transport->getMessageCount());

        $this->transport->reject($envelope2);
        $this->assertSame(0, $this->transport->getMessageCount());
    }

    public function testAll()
    {
        $this->assertSame([], $this->transport->all());

        $envelope1 = $this->transport->send(new Envelope(new \stdClass()));
        $envelope2 = $this->transport->send((new Envelope(new \stdClass()))->with(new DelayStamp(10_000)));
        $envelope3 = $this->transport->send(new Envelope(new \stdClass()));
        $this->assertSame([$envelope1, $envelope2, $envelope3], $this->transport->all());
        $this->assertSame([$envelope1, $envelope2], $this->transport->all(2));

        $this->transport->ack($envelope1);
        $this->transport->reject($envelope3);
        $this->assertSame([$envelope2], $this->transport->all());
        $this->assertSame([], $this->transport->get(), 'Delayed messages are listed but not received');
    }

    public function testAllWithSerialization()
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
        $this->assertEquals([$envelopeDecoded->with(new TransportMessageIdStamp(1))], $serializeTransport->all());
        $this->assertEquals($envelopeDecoded->with(new TransportMessageIdStamp(1)), $serializeTransport->find(1));
    }

    public function testFind()
    {
        $this->assertNull($this->transport->find(1));

        $envelope1 = $this->transport->send(new Envelope(new \stdClass()));
        $envelope2 = $this->transport->send(new Envelope(new \stdClass()));
        $this->assertSame($envelope1, $this->transport->find(1));
        $this->assertSame($envelope2, $this->transport->find('2'));
        $this->assertNull($this->transport->find(3));
        $this->assertNull($this->transport->find('foo'));
        $this->assertNull($this->transport->find([]));

        $this->transport->ack($envelope1);
        $this->assertNull($this->transport->find(1));
        $this->transport->reject($envelope2);
        $this->assertNull($this->transport->find(2));
    }

    public function testEnvelopesDispatchedInThisProcessAreHandedBackTrusted()
    {
        $this->transport->send(new Envelope(new \stdClass()));

        $this->assertTrue($this->transport->get()[0]->last(TrustStamp::class)?->isTrusted());
        $this->assertTrue($this->transport->all()[0]->last(TrustStamp::class)?->isTrusted());
        $this->assertTrue($this->transport->find(1)?->last(TrustStamp::class)?->isTrusted());
        $this->assertNull($this->transport->getSent()[0]->last(TrustStamp::class));
    }

    public function testEnvelopesReceivedFromATransportAreHandedBackWithTheirOwnTrust()
    {
        $untrusted = TrustStamp::untrusted();
        $trusted = TrustStamp::trusted();
        $this->transport->send(new Envelope(new \stdClass(), [new ReceivedStamp('async')]));
        $this->transport->send(new Envelope(new \stdClass(), [new ReceivedStamp('async'), $untrusted]));
        $this->transport->send(new Envelope(new \stdClass(), [new ReceivedStamp('scheduler_default'), $trusted]));

        [$received, $receivedUntrusted, $receivedTrusted] = $this->transport->get(3);

        $this->assertNull($received->last(TrustStamp::class));
        $this->assertSame($untrusted, $receivedUntrusted->last(TrustStamp::class));
        $this->assertSame($trusted, $receivedTrusted->last(TrustStamp::class));
    }

    public function testEnvelopesAreNotTrustedWhenTheyAreSerialized()
    {
        $transport = new InMemoryTransport(new PhpSerializer());
        $transport->send(new Envelope(new DummyMessage('Hello.')));

        $this->assertNull($transport->get()[0]->last(TrustStamp::class));
    }

    private function createUndecodableSerializer(): SerializerInterface
    {
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['body' => 'undecodable']);
        $serializer->method('decode')->willReturnCallback(static fn (array $encodedEnvelope) => MessageDecodingFailedException::wrap($encodedEnvelope, 'Cannot decode.'));

        return $serializer;
    }
}
