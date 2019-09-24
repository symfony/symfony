<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Messenger\Worker\StopWhenMessageCountIsExceededWorker;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @group time-sensitive
 */
class WorkerTest extends TestCase
{
    public function testWorkerDispatchTheReceivedMessage()
    {
        $apiMessage = new DummyMessage('API');
        $ipaMessage = new DummyMessage('IPA');

        $receiver = new DummyReceiver([
            [new Envelope($apiMessage), new Envelope($ipaMessage)],
        ]);

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();

        $bus->expects($this->at(0))->method('dispatch')->with(
            $envelope = new Envelope($apiMessage, [new ReceivedStamp('transport')])
        )->willReturn($envelope);

        $bus->expects($this->at(1))->method('dispatch')->with(
            $envelope = new Envelope($ipaMessage, [new ReceivedStamp('transport')])
        )->willReturn($envelope);

        $worker = new Worker(['transport' => $receiver], $bus);
        $worker->run([], function (?Envelope $envelope) use ($worker) {
            // stop after the messages finish
            if (null === $envelope) {
                $worker->stop();
            }
        });

        $this->assertSame(2, $receiver->getAcknowledgeCount());
    }

    public function testWorkerDoesNotWrapMessagesAlreadyWrappedWithReceivedMessage()
    {
        $envelope = new Envelope(new DummyMessage('API'));
        $receiver = new DummyReceiver([[$envelope]]);
        $envelope = $envelope->with(new ReceivedStamp('transport'));

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->expects($this->at(0))->method('dispatch')->with($envelope)->willReturn($envelope);

        $worker = new Worker(['transport' => $receiver], $bus, []);
        $worker->run([], function (?Envelope $envelope) use ($worker) {
            // stop after the messages finish
            if (null === $envelope) {
                $worker->stop();
            }
        });
    }

    public function testDispatchCausesRetry()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('Hello'), [new SentStamp('Some\Sender', 'transport1')])],
        ]);

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->expects($this->at(0))->method('dispatch')->willThrowException(new \InvalidArgumentException('Why not'));

        // 2nd call will be the retry
        $bus->expects($this->at(1))->method('dispatch')->with($this->callback(function (Envelope $envelope) {
            /** @var RedeliveryStamp|null $redeliveryStamp */
            $redeliveryStamp = $envelope->last(RedeliveryStamp::class);
            $this->assertNotNull($redeliveryStamp);
            // retry count now at 1
            $this->assertSame(1, $redeliveryStamp->getRetryCount());
            $this->assertSame('transport1', $redeliveryStamp->getSenderClassOrAlias());

            // received stamp is removed
            $this->assertNull($envelope->last(ReceivedStamp::class));

            return true;
        }))->willReturnArgument(0);

        $retryStrategy = $this->getMockBuilder(RetryStrategyInterface::class)->getMock();
        $retryStrategy->expects($this->once())->method('isRetryable')->willReturn(true);

        $worker = new Worker(['transport1' => $receiver], $bus, ['transport1' => $retryStrategy]);
        $worker->run([], function (?Envelope $envelope) use ($worker) {
            // stop after the messages finish
            if (null === $envelope) {
                $worker->stop();
            }
        });

        // old message acknowledged
        $this->assertSame(1, $receiver->getAcknowledgeCount());
    }

    public function testUnrecoverableMessageHandlingExceptionPreventsRetries()
    {
        $envelope1 = new Envelope(new DummyMessage('Unwrapped Exception'), [new SentStamp('Some\Sender', 'transport1')]);
        $envelope2 = new Envelope(new DummyMessage('Wrapped Exception'), [new SentStamp('Some\Sender', 'transport1')]);

        $receiver = new DummyReceiver([
            [$envelope1],
            [$envelope2],
        ]);

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->expects($this->at(0))->method('dispatch')->willThrowException(new UnrecoverableMessageHandlingException());
        $bus->expects($this->at(1))->method('dispatch')->willThrowException(
            new HandlerFailedException($envelope2, [new UnrecoverableMessageHandlingException()])
        );

        $retryStrategy = $this->getMockBuilder(RetryStrategyInterface::class)->getMock();
        $retryStrategy->expects($this->never())->method('isRetryable')->willReturn(true);

        $worker = new Worker(['transport1' => $receiver], $bus, ['transport1' => $retryStrategy]);
        $worker->run([], function (?Envelope $envelope) use ($worker) {
            // stop after the messages finish
            if (null === $envelope) {
                $worker->stop();
            }
        });

        // message was rejected
        $this->assertSame(2, $receiver->getRejectCount());
    }

    public function testDispatchCausesRejectWhenNoRetry()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('Hello'), [new SentStamp('Some\Sender', 'transport1')])],
        ]);

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->method('dispatch')->willThrowException(new \InvalidArgumentException('Why not'));

        $retryStrategy = $this->getMockBuilder(RetryStrategyInterface::class)->getMock();
        $retryStrategy->expects($this->once())->method('isRetryable')->willReturn(false);

        $worker = new Worker(['transport1' => $receiver], $bus, ['transport1' => $retryStrategy]);
        $worker->run([], function (?Envelope $envelope) use ($worker) {
            // stop after the messages finish
            if (null === $envelope) {
                $worker->stop();
            }
        });
        $this->assertSame(1, $receiver->getRejectCount());
        $this->assertSame(0, $receiver->getAcknowledgeCount());
    }

    public function testDispatchCausesRejectOnUnrecoverableMessage()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('Hello'))],
        ]);

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->method('dispatch')->willThrowException(new UnrecoverableMessageHandlingException('Will never work'));

        $retryStrategy = $this->getMockBuilder(RetryStrategyInterface::class)->getMock();
        $retryStrategy->expects($this->never())->method('isRetryable');

        $worker = new Worker(['transport1' => $receiver], $bus, ['transport1' => $retryStrategy]);
        $worker->run([], function (?Envelope $envelope) use ($worker) {
            // stop after the messages finish
            if (null === $envelope) {
                $worker->stop();
            }
        });
        $this->assertSame(1, $receiver->getRejectCount());
    }

    public function testWorkerDoesNotSendNullMessagesToTheBus()
    {
        $receiver = new DummyReceiver([
            null,
        ]);

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->expects($this->never())->method('dispatch');

        $worker = new Worker([$receiver], $bus);
        $worker->run([], function (?Envelope $envelope) use ($worker) {
            // stop after the messages finish
            if (null === $envelope) {
                $worker->stop();
            }
        });
    }

    public function testWorkerDispatchesEventsOnSuccess()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $receiver = new DummyReceiver([[$envelope]]);

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->method('dispatch')->willReturn($envelope);

        $eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $eventDispatcher->expects($this->exactly(3))
            ->method('dispatch')
            ->withConsecutive(
                [$this->isInstanceOf(WorkerMessageReceivedEvent::class)],
                [$this->isInstanceOf(WorkerMessageHandledEvent::class)],
                [$this->isInstanceOf(WorkerStoppedEvent::class)]
            );

        $worker = new Worker([$receiver], $bus, [], $eventDispatcher);
        $worker->run([], function (?Envelope $envelope) use ($worker) {
            // stop after the messages finish
            if (null === $envelope) {
                $worker->stop();
            }
        });
    }

    public function testWorkerDispatchesEventsOnError()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $receiver = new DummyReceiver([[$envelope]]);

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $exception = new \InvalidArgumentException('Oh no!');
        $bus->method('dispatch')->willThrowException($exception);

        $eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $eventDispatcher->expects($this->exactly(3))
            ->method('dispatch')
            ->withConsecutive(
                [$this->isInstanceOf(WorkerMessageReceivedEvent::class)],
                [$this->isInstanceOf(WorkerMessageFailedEvent::class)],
                [$this->isInstanceOf(WorkerStoppedEvent::class)]
            );

        $worker = new Worker([$receiver], $bus, [], $eventDispatcher);
        $worker->run([], function (?Envelope $envelope) use ($worker) {
            // stop after the messages finish
            if (null === $envelope) {
                $worker->stop();
            }
        });
    }

    public function testTimeoutIsConfigurable()
    {
        $apiMessage = new DummyMessage('API');
        $receiver = new DummyReceiver([
            [new Envelope($apiMessage), new Envelope($apiMessage)],
            [], // will cause a wait
            [], // will cause a wait
            [new Envelope($apiMessage)],
            [new Envelope($apiMessage)],
            [], // will cause a wait
            [new Envelope($apiMessage)],
        ]);

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();

        $worker = new Worker([$receiver], $bus);
        $receivedCount = 0;
        $startTime = microtime(true);
        // sleep .1 after each idle
        $worker->run(['sleep' => 100000], function (?Envelope $envelope) use ($worker, &$receivedCount, $startTime) {
            if (null !== $envelope) {
                ++$receivedCount;
            }

            if (5 === $receivedCount) {
                $worker->stop();
                $duration = microtime(true) - $startTime;

                // wait time should be .3 seconds
                // use .29 & .31 for timing "wiggle room"
                $this->assertGreaterThanOrEqual(.29, $duration);
                $this->assertLessThan(.31, $duration);
            }
        });
    }

    public function testWorkerWithMultipleReceivers()
    {
        // envelopes, in their expected delivery order
        $envelope1 = new Envelope(new DummyMessage('message1'));
        $envelope2 = new Envelope(new DummyMessage('message2'));
        $envelope3 = new Envelope(new DummyMessage('message3'));
        $envelope4 = new Envelope(new DummyMessage('message4'));
        $envelope5 = new Envelope(new DummyMessage('message5'));
        $envelope6 = new Envelope(new DummyMessage('message6'));

        /*
         * Round 1) receiver 1 & 2 have nothing, receiver 3 processes envelope1 and envelope2
         * Round 2) receiver 1 has nothing, receiver 2 processes envelope3, receiver 3 is not called
         * Round 3) receiver 1 processes envelope 4, receivers 2 & 3 are not called
         * Round 4) receiver 1 processes envelope 5, receivers 2 & 3 are not called
         * Round 5) receiver 1 has nothing, receiver 2 has nothing, receiver 3 has envelope 6
         */
        $receiver1 = new DummyReceiver([
            [],
            [],
            [$envelope4],
            [$envelope5],
            [],
        ]);
        $receiver2 = new DummyReceiver([
            [],
            [$envelope3],
            [],
        ]);
        $receiver3 = new DummyReceiver([
            [$envelope1, $envelope2],
            [],
            [$envelope6],
        ]);

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();

        $receivedCount = 0;
        $worker = new Worker([$receiver1, $receiver2, $receiver3], $bus);
        $processedEnvelopes = [];
        $worker->run([], function (?Envelope $envelope) use ($worker, &$receivedCount, &$processedEnvelopes) {
            if (null !== $envelope) {
                $processedEnvelopes[] = $envelope;
                ++$receivedCount;
            }

            // stop after the messages finish
            if (6 === $receivedCount) {
                $worker->stop();
            }
        });

        // make sure they were processed in the correct order
        $this->assertSame([$envelope1, $envelope2, $envelope3, $envelope4, $envelope5, $envelope6], $processedEnvelopes);
    }

    public function testWorkerWithDecorator()
    {
        $envelope1 = new Envelope(new DummyMessage('message1'));
        $envelope2 = new Envelope(new DummyMessage('message2'));
        $envelope3 = new Envelope(new DummyMessage('message3'));

        $receiver = new DummyReceiver([
            [$envelope1, $envelope2, $envelope3],
        ]);

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();

        $worker = new Worker([$receiver], $bus);
        $workerWithDecorator = new StopWhenMessageCountIsExceededWorker($worker, 2);
        $processedEnvelopes = [];
        $workerWithDecorator->run([], function (?Envelope $envelope) use (&$processedEnvelopes) {
            if (null !== $envelope) {
                $processedEnvelopes[] = $envelope;
            }
        });

        $this->assertSame([$envelope1, $envelope2], $processedEnvelopes);
    }
}

class DummyReceiver implements ReceiverInterface
{
    private $deliveriesOfEnvelopes;
    private $acknowledgeCount = 0;
    private $rejectCount = 0;

    public function __construct(array $deliveriesOfEnvelopes)
    {
        $this->deliveriesOfEnvelopes = $deliveriesOfEnvelopes;
    }

    public function get(): iterable
    {
        $val = array_shift($this->deliveriesOfEnvelopes);

        return null === $val ? [] : $val;
    }

    public function ack(Envelope $envelope): void
    {
        ++$this->acknowledgeCount;
    }

    public function reject(Envelope $envelope): void
    {
        ++$this->rejectCount;
    }

    public function getAcknowledgeCount(): int
    {
        return $this->acknowledgeCount;
    }

    public function getRejectCount(): int
    {
        return $this->rejectCount;
    }
}
