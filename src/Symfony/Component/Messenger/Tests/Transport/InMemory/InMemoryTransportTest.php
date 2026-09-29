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
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\DecodeFailedMessageMiddleware;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Tests\Fixtures\AnEnvelopeStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
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

    private function createUndecodableSerializer(): SerializerInterface
    {
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['body' => 'undecodable']);
        $serializer->method('decode')->willReturnCallback(static fn (array $encodedEnvelope) => MessageDecodingFailedException::wrap($encodedEnvelope, 'Cannot decode.'));

        return $serializer;
    }
}
