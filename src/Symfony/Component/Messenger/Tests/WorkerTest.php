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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServicesResetter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Cooldown\CooldownStatus;
use Symfony\Component\Messenger\Cooldown\CooldownStrategyInterface;
use Symfony\Component\Messenger\Cooldown\MultiplierCooldownStrategy;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRateLimitedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\Event\WorkerTransportCoolingDownEvent;
use Symfony\Component\Messenger\EventListener\ResetMemoryUsageListener;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnIdleListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Execution\DeferredBatchMessageQueue;
use Symfony\Component\Messenger\Execution\MessageExecutionStrategyInterface;
use Symfony\Component\Messenger\Execution\ParallelExecutionStrategy;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\FlushBatchHandlersStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentForRetryStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TrustStamp;
use Symfony\Component\Messenger\Tests\Fixtures\App\ConnectionStamp;
use Symfony\Component\Messenger\Tests\Fixtures\App\HandledByBusStamp;
use Symfony\Component\Messenger\Tests\Fixtures\App\SeenByWorkerStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyReceiver;
use Symfony\Component\Messenger\Tests\Fixtures\ResettableDummyReceiver;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\QueueReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Reservation;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[Group('time-sensitive')]
class WorkerTest extends TestCase
{
    private string $console = __DIR__.'/Fixtures/App/bin/console';

    public function testWorkerDispatchTheReceivedMessage()
    {
        $apiMessage = new DummyMessage('API');
        $ipaMessage = new DummyMessage('IPA');

        $receiver = new DummyReceiver([
            [new Envelope($apiMessage), new Envelope($ipaMessage)],
        ]);

        $bus = $this->createMock(MessageBusInterface::class);
        $envelopes = [];

        $bus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function ($envelope) use (&$envelopes) {
                return $envelopes[] = $envelope;
            });

        $dispatcher = new class implements EventDispatcherInterface {
            private StopWorkerOnMessageLimitListener $listener;

            public function __construct()
            {
                $this->listener = new StopWorkerOnMessageLimitListener(2);
            }

            public function dispatch(object $event): object
            {
                if ($event instanceof WorkerRunningEvent) {
                    $this->listener->onWorkerRunning($event);
                }

                return $event;
            }
        };

        $worker = new Worker(['transport' => $receiver], $bus, $dispatcher, clock: new MockClock());
        $worker->run();

        $this->assertSame($apiMessage, $envelopes[0]->getMessage());
        $this->assertSame($ipaMessage, $envelopes[1]->getMessage());
        $this->assertCount(1, $envelopes[0]->all(ReceivedStamp::class));
        $this->assertCount(1, $envelopes[0]->all(ConsumedByWorkerStamp::class));
        $this->assertSame('transport', $envelopes[0]->last(ReceivedStamp::class)->getTransportName());

        $this->assertSame(2, $receiver->getAcknowledgeCount());
    }

    public function testBusNameOptionOverridesTheStampedBusName()
    {
        $envelope = new Envelope(new DummyMessage('Hello'), [new BusNameStamp('origin.bus')]);
        $receiver = new DummyReceiver([[$envelope]]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(
                static fn (Envelope $e) => 1 === \count($e->all(BusNameStamp::class))
                    && 'forced.bus' === $e->last(BusNameStamp::class)->getBusName()
            ))
            ->willReturnArgument(0);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $worker = new Worker(['transport' => $receiver], $bus, $dispatcher, clock: new MockClock());
        $worker->run(['bus_name' => 'forced.bus']);
    }

    public function testFuturesAreAcknowledgedOnTheirOriginatingTransport()
    {
        $receiverWithoutMessages = new DummyReceiver([
            [],
            [],
        ]);
        $receiverWithFuture = new DummyReceiver([
            [
                new Envelope(new DummyMessage('API')),
            ],
            [],
        ]);

        $bus = new MessageBus();

        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                if ($event instanceof WorkerRunningEvent && $event->isWorkerIdle()) {
                    $event->getWorker()->stop();
                }

                return $event;
            }
        };

        $worker = new Worker([
            'secondary' => $receiverWithoutMessages,
            'primary' => $receiverWithFuture,
        ], $bus, $dispatcher, clock: new MockClock(), messageExecutionStrategy: $messageExecutionStrategy = new ParallelExecutionStrategy($this->console));
        $worker->run();
        $messageExecutionStrategy->shutdown();

        $this->assertSame(0, $receiverWithoutMessages->getAcknowledgeCount());
        $this->assertSame(1, $receiverWithFuture->getAcknowledgeCount());
    }

    public function testParallelExecutionPreservesWorkerStamps()
    {
        $apiMessage = new DummyMessage('API');
        $ipaMessage = new DummyMessage('IPA');

        $receiver = new DummyReceiver([
            [new Envelope($apiMessage), new Envelope($ipaMessage)],
        ]);

        $dispatcher = new class implements EventDispatcherInterface {
            private StopWorkerOnMessageLimitListener $listener;
            public array $handledEnvelopes = [];

            public function __construct()
            {
                $this->listener = new StopWorkerOnMessageLimitListener(2);
            }

            public function dispatch(object $event): object
            {
                if ($event instanceof WorkerMessageHandledEvent) {
                    $this->handledEnvelopes[] = $event->getEnvelope();
                }

                if ($event instanceof WorkerRunningEvent) {
                    $this->listener->onWorkerRunning($event);
                }

                return $event;
            }
        };

        $messageExecutionStrategy = new ParallelExecutionStrategy($this->console);
        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, clock: new MockClock(), messageExecutionStrategy: $messageExecutionStrategy);

        $worker->run();
        $messageExecutionStrategy->shutdown();

        $envelope = $dispatcher->handledEnvelopes[0];
        $envelopeOther = $dispatcher->handledEnvelopes[1];

        $this->assertSame($apiMessage->getMessage(), $envelope->getMessage()->getMessage());
        $this->assertSame($ipaMessage->getMessage(), $envelopeOther->getMessage()->getMessage());
        $this->assertCount(1, $envelope->all(ReceivedStamp::class));
        $this->assertCount(1, $envelope->all(ConsumedByWorkerStamp::class));
        $this->assertSame('transport', $envelope->last(ReceivedStamp::class)->getTransportName());
    }

    public function testParallelExecutionUsesExplicitBusName()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('API'))],
        ]);

        $dispatcher = new class implements EventDispatcherInterface {
            private StopWorkerOnMessageLimitListener $listener;

            public function __construct()
            {
                $this->listener = new StopWorkerOnMessageLimitListener(1);
            }

            public function dispatch(object $event): object
            {
                if ($event instanceof WorkerRunningEvent) {
                    $this->listener->onWorkerRunning($event);
                }

                return $event;
            }
        };

        $messageExecutionStrategy = new ParallelExecutionStrategy($this->console);
        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, clock: new MockClock(), messageExecutionStrategy: $messageExecutionStrategy);

        $worker->run([
            'bus_name' => 'other.bus',
        ]);
        $messageExecutionStrategy->shutdown();

        $this->assertSame('other.bus', $receiver->getAcknowledgedEnvelopes()[0]->last(HandledByBusStamp::class)?->getBusName());
    }

    public function testParallelExecutionFlushesBatchOnMessageLimit()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('API'))],
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $messageExecutionStrategy = new ParallelExecutionStrategy($this->console);
        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, clock: new MockClock(), messageExecutionStrategy: $messageExecutionStrategy);

        $worker->run();
        $messageExecutionStrategy->shutdown();

        $this->assertSame(1, $receiver->getAcknowledgeCount());
        $this->assertSame(1, $receiver->getAcknowledgedEnvelopes()[0]->last(HandledStamp::class)?->getResult());
    }

    public function testParallelExecutionFlushesBatchWhenChildMemoryLimitIsReached()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('API'))],
            [],
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        });

        $messageExecutionStrategy = new ParallelExecutionStrategy($this->console, memoryLimit: 1);
        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, clock: new MockClock(), messageExecutionStrategy: $messageExecutionStrategy);

        $worker->run();
        $messageExecutionStrategy->shutdown();

        $this->assertSame(1, $receiver->getAcknowledgeCount());
        $this->assertSame(1, $receiver->getAcknowledgedEnvelopes()[0]->last(HandledStamp::class)?->getResult());
    }

    public function testParallelExecutionBatchesMessagesOnTheSameTask()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('Hey'))],
            [new Envelope(new DummyMessage('Bob'))],
            [],
        ]);

        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                static $calls = 0;

                if ($event instanceof WorkerRunningEvent && 3 <= ++$calls) {
                    $event->getWorker()->stop();
                }

                return $event;
            }
        };

        $messageExecutionStrategy = new ParallelExecutionStrategy($this->console);
        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, clock: new MockClock(), messageExecutionStrategy: $messageExecutionStrategy);

        $worker->run();
        $messageExecutionStrategy->shutdown();

        $this->assertSame([2, 2], array_map(static fn (Envelope $envelope) => $envelope->last(HandledStamp::class)?->getResult(), $receiver->getAcknowledgedEnvelopes()));
    }

    public function testParallelExecutionRemembersBatchingKeysAfterThePreviousBatchWasFlushed()
    {
        $messageExecutionStrategy = new ParallelExecutionStrategy($this->console);

        $firstReceiver = new DummyReceiver([
            [new Envelope(new DummyMessage('first'))],
        ]);

        $firstDispatcher = new EventDispatcher();
        $firstDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $firstWorker = new Worker(['transport' => $firstReceiver], new MessageBus(), $firstDispatcher, clock: new MockClock(), messageExecutionStrategy: $messageExecutionStrategy);

        $firstWorker->run();

        $this->assertSame(1, $firstReceiver->getAcknowledgedEnvelopes()[0]->last(HandledStamp::class)?->getResult());

        $secondReceiver = new DummyReceiver([
            [new Envelope(new DummyMessage('second')), new Envelope(new DummyMessage('third'))],
            [],
        ]);

        $secondDispatcher = new EventDispatcher();
        $secondDispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) use ($secondReceiver) {
            if ($event->isWorkerIdle() && 0 < $secondReceiver->getAcknowledgeCount()) {
                $event->getWorker()->stop();
            }
        });

        $secondWorker = new Worker(['transport' => $secondReceiver], new MessageBus(), $secondDispatcher, clock: new MockClock(), messageExecutionStrategy: $messageExecutionStrategy);

        $secondWorker->run();
        $messageExecutionStrategy->shutdown();

        $handledBatches = [];

        foreach ($secondReceiver->getAcknowledgedEnvelopes() as $envelope) {
            $handledBatches[$envelope->getMessage()->getMessage()] = $envelope->last(HandledStamp::class)?->getResult();
        }

        $this->assertSame(['second' => 2, 'third' => 2], $handledBatches);
    }

    public function testWorkerFetchesAgainWhenTheBufferedEnvelopesAreDrained()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('first')), new Envelope(new DummyMessage('second'))],
            [new Envelope(new DummyMessage('third')), new Envelope(new DummyMessage('fourth'))],
            [],
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnIdleListener());

        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, null, null, new MockClock(), new PausingExecutionStrategy());
        $worker->run(['fetch_size' => 2]);

        $this->assertSame(4, $receiver->getAcknowledgeCount());
    }

    public function testDrainedBufferedEnvelopesDoNotDelayHigherPriorityReceivers()
    {
        $highPriorityReceiver = new DummyReceiver([
            [],
            [new Envelope(new DummyMessage('high'))],
        ]);
        $lowPriorityReceiver = new DummyReceiver([
            [new Envelope(new DummyMessage('low1')), new Envelope(new DummyMessage('low2'))],
            [new Envelope(new DummyMessage('low3')), new Envelope(new DummyMessage('low4'))],
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnIdleListener());

        $strategy = new PausingExecutionStrategy();
        $worker = new Worker(['high' => $highPriorityReceiver, 'low' => $lowPriorityReceiver], new MessageBus(), $dispatcher, null, null, new MockClock(), $strategy);
        $worker->run(['fetch_size' => 2]);

        $this->assertSame(['low1', 'low2', 'high', 'low3', 'low4'], $strategy->executed);
    }

    public function testStopWhenEmptyHandlesTheMessagesDispatchedByPendingBatches()
    {
        $receiver = new AppendableReceiver([new Envelope(new DummyMessage('first'))]);
        $handler = new FollowUpBatchHandler($receiver);
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [new HandlerDescriptor($handler)]]))]);

        $worker = new Worker(['transport' => $receiver], $bus, null, null, null, new MockClock());
        $worker->run(['stop_when_empty' => true, 'time_limit' => 10]);

        $this->assertSame(['first', 'follow-up'], $receiver->acked);
        $this->assertSame([], $receiver->queue);
    }

    public function testStopWhenEmptyHandlesTheMessagesDispatchedByTheMessagesInFlight()
    {
        $receiver = new AppendableReceiver([new Envelope(new DummyMessage('first'))]);
        $strategy = new DeferringExecutionStrategy(static function (Envelope $envelope) use ($receiver) {
            if ('first' === $envelope->getMessage()->getMessage()) {
                $receiver->queue[] = new Envelope(new DummyMessage('follow-up'));
            }
        });

        $worker = new Worker(['transport' => $receiver], new MessageBus(), null, null, null, new MockClock(), $strategy);
        $worker->run(['stop_when_empty' => true, 'time_limit' => 10]);

        $this->assertSame(['first', 'follow-up'], $receiver->acked);
        $this->assertSame([], $receiver->queue);
    }

    #[DataProvider('provideIdleWaits')]
    public function testIdleWorkerWaitsForTheMessagesInFlightInsteadOfSleeping(float $waited, bool $handled, float $expectedElapsed)
    {
        $clock = new MockClock();
        $strategy = new class($clock, $waited, $handled) implements MessageExecutionStrategyInterface {
            public array $timeouts = [];

            public function __construct(
                private MockClock $clock,
                private float $waited,
                private bool $handled,
            ) {
            }

            public function execute(Envelope $envelope, string $transportName, callable $onHandled): void
            {
            }

            public function shouldPauseConsumption(): bool
            {
                return false;
            }

            public function wait(callable $onHandled, ?float $timeout = null): bool
            {
                $this->timeouts[] = $timeout;
                $this->clock->sleep($this->waited);

                return $this->handled;
            }

            public function flush(callable $onHandled, bool|float $force = false): bool
            {
                return false;
            }

            public function shutdown(): void
            {
            }
        };

        $idleEvents = 0;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) use (&$idleEvents) {
            if ($event->isWorkerIdle() && 2 <= ++$idleEvents) {
                $event->getWorker()->stop();
            }
        });

        $worker = new Worker(['transport' => new DummyReceiver([])], new MessageBus(), $dispatcher, clock: $clock, messageExecutionStrategy: $strategy);
        $now = $clock->now();
        $start = $now->getTimestamp() + $now->getMicrosecond() / 1e6;
        $worker->run(['sleep' => 1000000]);
        $now = $clock->now();

        $this->assertSame([1.0], $strategy->timeouts);
        $this->assertEqualsWithDelta($expectedElapsed, $now->getTimestamp() + $now->getMicrosecond() / 1e6 - $start, 0.001);
    }

    public static function provideIdleWaits(): iterable
    {
        yield 'a message was handled' => [0.0, true, 0.0];
        yield 'nothing was handled before the timeout' => [1.0, false, 1.0];
        yield 'nothing was in flight' => [0.0, false, 1.0];
    }

    public function testParallelExecutionFailureKeepsDebugInfoWithoutLeakingControlStamp()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('fail'))],
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $failedEvent = null;
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$failedEvent) {
            $failedEvent = $event;
        });

        $messageExecutionStrategy = new ParallelExecutionStrategy($this->console);
        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, clock: new MockClock(), messageExecutionStrategy: $messageExecutionStrategy);

        $worker->run();
        $messageExecutionStrategy->shutdown();

        $this->assertInstanceOf(WorkerMessageFailedEvent::class, $failedEvent);
        $this->assertSame(1, $receiver->getRejectCount());
        $this->assertNull($failedEvent->getEnvelope()->last(NoAutoAckStamp::class));

        $throwable = $failedEvent->getThrowable();
        $this->assertInstanceOf(HandlerFailedException::class, $throwable);
        $this->assertNull($throwable->getEnvelope()?->last(NoAutoAckStamp::class));
    }

    public function testParallelExecutionSendsNonSendableStampsAndAcknowledgesWithTheParentInstances()
    {
        $stamp = new ConnectionStamp('42');
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('API'), [$stamp])],
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $messageExecutionStrategy = new ParallelExecutionStrategy($this->console);
        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, clock: new MockClock(), messageExecutionStrategy: $messageExecutionStrategy);

        $worker->run();
        $messageExecutionStrategy->shutdown();

        $envelope = $receiver->getAcknowledgedEnvelopes()[0];
        $this->assertContains(ConnectionStamp::class, $envelope->last(SeenByWorkerStamp::class)?->stampClasses ?? []);
        $this->assertSame($stamp, $envelope->last(ConnectionStamp::class));
    }

    #[DataProvider('provideTrustStamps')]
    public function testParallelExecutionTellsTheWorkerWhetherTheEnvelopeIsTrusted(TrustStamp $trustStamp, bool $trusted)
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('API'), [$trustStamp])],
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $messageExecutionStrategy = new ParallelExecutionStrategy($this->console);
        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, clock: new MockClock(), messageExecutionStrategy: $messageExecutionStrategy);

        $worker->run();
        $messageExecutionStrategy->shutdown();

        $envelope = $receiver->getAcknowledgedEnvelopes()[0];
        $this->assertSame($trusted, $envelope->last(SeenByWorkerStamp::class)?->trusted);
        $this->assertSame($trustStamp, $envelope->last(TrustStamp::class));
    }

    public static function provideTrustStamps(): iterable
    {
        yield 'trusted' => [TrustStamp::trusted(), true];
        yield 'untrusted' => [TrustStamp::untrusted(), false];
    }

    #[DataProvider('provideEnvelopesThatCannotBeSerialized')]
    public function testParallelExecutionStopsOnAnEnvelopeThatCannotBeSerialized(Envelope $envelope, string $expectedMessage)
    {
        $receiver = new DummyReceiver([[$envelope]]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        });

        $messageExecutionStrategy = new ParallelExecutionStrategy($this->console);
        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, clock: new MockClock(), messageExecutionStrategy: $messageExecutionStrategy);
        $failure = null;

        try {
            $worker->run();
        } catch (LogicException $failure) {
        } finally {
            $messageExecutionStrategy->shutdown();
        }

        $this->assertInstanceOf(LogicException::class, $failure);
        $this->assertSame($expectedMessage, $failure->getMessage());
        $this->assertSame(0, $receiver->getAcknowledgeCount());
        $this->assertSame(0, $receiver->getRejectCount());
    }

    public static function provideEnvelopesThatCannotBeSerialized(): iterable
    {
        yield 'stamp' => [
            new Envelope(new DummyMessage('live'), [new ConnectionStamp(static fn () => null)]),
            'Cannot send message "'.DummyMessage::class.'" to a parallel worker: its "'.ConnectionStamp::class.'" stamp cannot be serialized. Make the stamp serializable, for example by implementing "__serialize()", or consume its transport without the "--concurrency" option.',
        ];
        yield 'message' => [
            new Envelope(new \ArrayObject([static fn () => null])),
            'Cannot send message "ArrayObject" to a parallel worker: it cannot be serialized.',
        ];
    }

    public function testHandlingErrorCausesReject()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('Hello'), [new SentStamp('Some\Sender', 'transport1')])],
        ]);

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new \InvalidArgumentException('Why not'));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $worker = new Worker(['transport1' => $receiver], $bus, $dispatcher, clock: new MockClock());
        $worker->run();

        $this->assertSame(1, $receiver->getRejectCount());
        $this->assertSame(0, $receiver->getAcknowledgeCount());
    }

    public function testWorkerResetsConnectionIfReceiverIsResettable()
    {
        $resettableReceiver = new ResettableDummyReceiver([]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ResetServicesListener(new ServicesResetter(new \ArrayIterator([$resettableReceiver]), ['reset'])));

        $worker = new Worker([$resettableReceiver], new MessageBus(), $dispatcher, clock: new MockClock());
        $worker->stop();
        $worker->run();
        $this->assertTrue($resettableReceiver->hasBeenReset());
    }

    public function testWorkerResetsTransportsIfResetServicesListenerIsCalled()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $resettableReceiver = new ResettableDummyReceiver([[$envelope]]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ResetServicesListener(new ServicesResetter(new \ArrayIterator([$resettableReceiver]), ['reset'])));
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) {
            $event->getWorker()->stop();
        });

        $worker = new Worker([$resettableReceiver], new MessageBus(), $dispatcher, clock: new MockClock());
        $worker->run();
        $this->assertTrue($resettableReceiver->hasBeenReset());
    }

    public function testWorkerDoesNotResetTransportsIfResetServicesListenerIsNotCalled()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $resettableReceiver = new ResettableDummyReceiver([[$envelope]]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) {
            $event->getWorker()->stop();
        });

        $worker = new Worker([$resettableReceiver], new MessageBus(), $dispatcher, clock: new MockClock());
        $worker->run();
        $this->assertFalse($resettableReceiver->hasBeenReset());
    }

    public function testWorkerDoesNotSendNullMessagesToTheBus()
    {
        $receiver = new DummyReceiver([
            null,
        ]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) {
            $event->getWorker()->stop();
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, clock: new MockClock());
        $worker->run();
    }

    public function testWorkerDispatchesEventsOnSuccess()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $receiver = new DummyReceiver([[$envelope]]);

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturn($envelope);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $series = [
            $this->isInstanceOf(WorkerStartedEvent::class),
            $this->isInstanceOf(WorkerMessageReceivedEvent::class),
            $this->isInstanceOf(WorkerMessageHandledEvent::class),
            $this->isInstanceOf(WorkerRunningEvent::class),
            $this->isInstanceOf(WorkerStoppedEvent::class),
        ];

        $eventDispatcher->expects($this->exactly(5))
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$series) {
                array_shift($series)->evaluate($event);

                if ($event instanceof WorkerRunningEvent) {
                    $event->getWorker()->stop();
                }

                return $event;
            });

        $worker = new Worker([$receiver], $bus, $eventDispatcher, clock: new MockClock());
        $worker->run();
    }

    public function testWorkerWithoutDispatcher()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $receiver = new DummyReceiver([[$envelope]]);

        $bus = $this->createMock(MessageBusInterface::class);
        $worker = new Worker([$receiver], $bus, clock: new MockClock());

        $bus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function () use ($worker, $envelope) {
                $worker->stop();

                return $envelope;
            });

        $worker->run();
    }

    public function testWorkerDispatchesEventsOnError()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $receiver = new DummyReceiver([[$envelope]]);

        $bus = $this->createStub(MessageBusInterface::class);
        $exception = new \InvalidArgumentException('Oh no!');
        $bus->method('dispatch')->willThrowException($exception);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $series = [
            $this->isInstanceOf(WorkerStartedEvent::class),
            $this->isInstanceOf(WorkerMessageReceivedEvent::class),
            $this->isInstanceOf(WorkerMessageFailedEvent::class),
            $this->isInstanceOf(WorkerRunningEvent::class),
            $this->isInstanceOf(WorkerStoppedEvent::class),
        ];

        $eventDispatcher->expects($this->exactly(5))
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$series) {
                array_shift($series)->evaluate($event);

                if ($event instanceof WorkerRunningEvent) {
                    $event->getWorker()->stop();
                }

                return $event;
            });

        $worker = new Worker([$receiver], $bus, $eventDispatcher, clock: new MockClock());
        $worker->run();
    }

    public function testWorkerContainsMetadata()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $receiver = new DummyQueueReceiver([[$envelope]]);

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturn($envelope);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) {
            $event->getWorker()->stop();
        });

        $worker = new Worker(['dummyReceiver' => $receiver], $bus, $dispatcher, clock: new MockClock());
        $worker->run(['queues' => ['queue1', 'queue2']]);

        $workerMetadata = $worker->getMetadata();

        $this->assertSame(['queue1', 'queue2'], $workerMetadata->getQueueNames());
        $this->assertSame(['dummyReceiver'], $workerMetadata->getTransportNames());
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

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(5));

        $clock = new MockClock('2023-03-19 14:00:00+00:00');
        $worker = new Worker([$receiver], new MessageBus(), $dispatcher, clock: $clock);
        $worker->run(['sleep' => 1000000]);
        $this->assertEquals(new \DateTimeImmutable('2023-03-19 14:00:03+00:00'), $clock->now());
    }

    public function testWorkerDoesNotSleepWhenStoppedDuringIdleRunningEvent()
    {
        $clock = new MockClock('2023-03-19 14:00:00+00:00');
        $receiver = new DummyReceiver([[]]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        });

        $worker = new Worker([$receiver], new MessageBus(), $dispatcher, clock: $clock);
        $worker->run(['sleep' => 1000000]);

        $this->assertEquals(new \DateTimeImmutable('2023-03-19 14:00:00+00:00'), $clock->now());
    }

    public function testWorkerCapsIdleSleepToRemainingTimeLimitAfterMessageConsumption()
    {
        $clock = new MockClock('2023-03-19 14:00:00+00:00');
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('API'))],
            [],
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) use ($clock) {
            if (!$event->isWorkerIdle()) {
                $clock->sleep(50);
            }
        });

        $worker = new Worker([$receiver], new MessageBus(), $dispatcher, clock: $clock);
        $worker->run(['sleep' => 20000000, 'time_limit' => 60]);

        $this->assertEquals(new \DateTimeImmutable('2023-03-19 14:01:00+00:00'), $clock->now());
    }

    public function testWorkerLogsWhenStoppedDueToTimeLimit()
    {
        $clock = new MockClock('2023-03-19 14:00:00+00:00');
        $receiver = new DummyReceiver([[]]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('info')
            ->willReturnCallback(function (string $message, array $context = []) use (&$timeLimitLogged) {
                if ('Worker stopped due to time limit of {timeLimit}s exceeded' === $message) {
                    $this->assertSame(['timeLimit' => 60], $context);
                    $timeLimitLogged = true;
                }
            });

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) use ($clock) {
            if ($event->isWorkerIdle()) {
                $clock->sleep(70);
            }
        });

        $worker = new Worker([$receiver], new MessageBus(), $dispatcher, $logger, clock: $clock);
        $worker->run(['sleep' => 1000000, 'time_limit' => 60]);

        $this->assertTrue($timeLimitLogged ?? false);
    }

    public function testWorkerReachesTheTimeLimitWhenOneMicrosecondRemains()
    {
        $clock = new MockClock('2023-03-19 14:00:00+00:00');
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('API'))],
        ]);

        $idleCount = 0;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) use ($clock, &$idleCount) {
            if (!$event->isWorkerIdle()) {
                $clock->sleep(59.999999);
            } elseif (10 < ++$idleCount) {
                // the worker would loop forever without sleeping otherwise
                $event->getWorker()->stop();
            }
        });

        $worker = new Worker([$receiver], new MessageBus(), $dispatcher, null, null, $clock);
        $worker->run(['sleep' => 1000000, 'time_limit' => 60]);

        $this->assertEquals(new \DateTimeImmutable('2023-03-19 14:01:00+00:00'), $clock->now());
        $this->assertSame(1, $idleCount);
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

        $processedEnvelopes = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(6));
        $dispatcher->addListener(WorkerMessageReceivedEvent::class, static function (WorkerMessageReceivedEvent $event) use (&$processedEnvelopes) {
            $processedEnvelopes[] = $event->getEnvelope();
        });
        $worker = new Worker([$receiver1, $receiver2, $receiver3], new MessageBus(), $dispatcher, clock: new MockClock());
        $worker->run();

        // make sure they were processed in the correct order
        $this->assertSame([$envelope1, $envelope2, $envelope3, $envelope4, $envelope5, $envelope6], $processedEnvelopes);
    }

    public function testWorkerLimitQueues()
    {
        $envelope = [new Envelope(new DummyMessage('message1'))];
        $receiver = new DummyQueueReceiver([$envelope]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, clock: new MockClock());
        $worker->run(['queues' => ['foo']]);

        $this->assertSame([['foo']], $receiver->queueNames);
        $this->assertSame([1], $receiver->getFromQueuesFetchSizes);
    }

    public function testWorkerPassesFetchSizeToReceiver()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('message1'))],
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, clock: new MockClock());
        $worker->run(['fetch_size' => 7]);

        $this->assertSame([7], $receiver->getFetchSizes());
    }

    public function testWorkerPassesFetchSizeToQueueReceiver()
    {
        $receiver = new DummyQueueReceiver([
            [new Envelope(new DummyMessage('message1'))],
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, clock: new MockClock());
        $worker->run(['queues' => ['foo'], 'fetch_size' => 7]);

        $this->assertSame([['foo']], $receiver->queueNames);
        $this->assertSame([7], $receiver->getFromQueuesFetchSizes);
    }

    public function testWorkerLimitQueuesUnsupported()
    {
        $receiver1 = $this->createStub(QueueReceiverInterface::class);
        $receiver2 = $this->createStub(ReceiverInterface::class);

        $worker = new Worker(['transport1' => $receiver1, 'transport2' => $receiver2], new MessageBus(), clock: new MockClock());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('Receiver for "transport2" does not implement "%s".', QueueReceiverInterface::class));
        $worker->run(['queues' => ['foo']]);
    }

    public function testWorkerMessageReceivedEventMutability()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $receiver = new DummyReceiver([[$envelope]]);

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnArgument(0);

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $stamp = new class implements StampInterface {
        };
        $listener = static function (WorkerMessageReceivedEvent $event) use ($stamp) {
            $event->addStamps($stamp);
        };

        $eventDispatcher->addListener(WorkerMessageReceivedEvent::class, $listener);

        $worker = new Worker([$receiver], $bus, $eventDispatcher, clock: new MockClock());
        $worker->run();

        $envelope = current($receiver->getAcknowledgedEnvelopes());
        $this->assertCount(1, $envelope->all($stamp::class));
    }

    public function testWorkerRateLimitMessages()
    {
        $envelope = [
            new Envelope(new DummyMessage('message1')),
            new Envelope(new DummyMessage('message2')),
            new Envelope(new DummyMessage('message3')),
            new Envelope(new DummyMessage('message4')),
        ];
        $receiver = new DummyReceiver([$envelope]);

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnArgument(0);

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(4));

        $rateLimitCount = 0;
        $eventDispatcher->addListener(WorkerRateLimitedEvent::class, static function () use (&$rateLimitCount) {
            ++$rateLimitCount;
        });

        $rateLimitFactory = new RateLimiterFactory([
            'id' => 'bus',
            'policy' => 'fixed_window',
            'limit' => 1,
            'interval' => '1 minute',
        ], new InMemoryStorage());

        ClockMock::register(Reservation::class);
        ClockMock::register(InMemoryStorage::class);

        $worker = new Worker(['bus' => $receiver], $bus, $eventDispatcher, null, ['bus' => $rateLimitFactory], new MockClock());
        $worker->run();

        $this->assertSame(4, $receiver->getAcknowledgeCount());
        $this->assertSame(3, $rateLimitCount);
    }

    public function testWorkerKeepsConsumingOtherTransportsWhileOneCoolsDown()
    {
        $failingReceiver = new DummyReceiver([
            [new Envelope(new DummyMessage('fails'))],
            [new Envelope(new DummyMessage('fails'))],
            [new Envelope(new DummyMessage('succeeds'))],
        ]);
        $otherReceiver = new DummyReceiver([
            [new Envelope(new DummyMessage('other 1'))],
            [new Envelope(new DummyMessage('other 2'))],
        ]);

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (Envelope $envelope) {
            if ('fails' === $envelope->getMessage()->getMessage()) {
                throw new \RuntimeException('Failure.');
            }

            return $envelope;
        });

        $cooldownEvents = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(5));
        $dispatcher->addListener(WorkerTransportCoolingDownEvent::class, static function (WorkerTransportCoolingDownEvent $event) use (&$cooldownEvents) {
            $cooldownEvents[] = [$event->getTransportName(), $event->getStatus()->getEnd()];
        });

        $clock = new MockClock('2026-10-14 10:00:00+00:00');
        $cooldownStrategy = new MultiplierCooldownStrategy(1, 10000, 2, 60000, 0, null, $clock);
        $worker = new Worker(['failing' => $failingReceiver, 'other' => $otherReceiver], $bus, $dispatcher, null, null, $clock, null, ['failing' => $cooldownStrategy]);
        $worker->run(['sleep' => 20000000]);

        // the failing transport is fetched again as soon as each cooldown ends, not after the 20s of the sleep option,
        // and the second cooldown lasts twice as long as the first
        $this->assertEquals(new \DateTimeImmutable('2026-10-14 10:00:30+00:00'), $clock->now());
        $this->assertCount(3, $failingReceiver->getFetchSizes());
        $this->assertSame(2, $failingReceiver->getRejectCount());
        $this->assertSame(1, $failingReceiver->getAcknowledgeCount());
        $this->assertSame(2, $otherReceiver->getAcknowledgeCount());

        $this->assertEquals([
            ['failing', new \DateTimeImmutable('2026-10-14 10:00:10+00:00')],
            ['failing', new \DateTimeImmutable('2026-10-14 10:00:30+00:00')],
        ], $cooldownEvents);
    }

    public function testWorkerReportsOutcomesToTheCooldownStrategy()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('fails'))],
            [new Envelope(new DummyMessage('succeeds'))],
        ]);

        $exception = new \RuntimeException('Failure.');
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (Envelope $envelope) use ($exception) {
            if ('fails' === $envelope->getMessage()->getMessage()) {
                throw $exception;
            }

            return $envelope;
        });

        $cooldownStrategy = new class implements CooldownStrategyInterface {
            public array $calls = [];

            public function checkStatus(string $transportName): CooldownStatus
            {
                $this->calls[] = ['checkStatus', $transportName];

                return CooldownStatus::inactive();
            }

            public function recordSuccess(string $transportName, Envelope $envelope): void
            {
                $this->calls[] = ['recordSuccess', $transportName, $envelope->getMessage()->getMessage()];
            }

            public function recordFailure(string $transportName, ?Envelope $envelope, \Throwable $throwable): void
            {
                $this->calls[] = ['recordFailure', $transportName, $envelope->getMessage()->getMessage(), $envelope->last(SentForRetryStamp::class)?->isSent, $throwable];
            }
        };

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) {
            $event->addStamps(new SentForRetryStamp(true));
        });
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(2));

        $worker = new Worker(['transport' => $receiver], $bus, $dispatcher, null, null, new MockClock(), null, ['transport' => $cooldownStrategy]);
        $worker->run();

        // the failure is recorded after the listeners of the failed event, so the strategy sees the retry stamps
        $this->assertSame([
            ['checkStatus', 'transport'],
            ['recordFailure', 'transport', 'fails', true, $exception],
            ['checkStatus', 'transport'],
            ['recordSuccess', 'transport', 'succeeds'],
        ], $cooldownStrategy->calls);
    }

    public function testWorkerSleepsAsUsualDuringACooldownWithoutAKnownEnd()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('Hello'))],
        ]);

        $cooldownStrategy = new class implements CooldownStrategyInterface {
            private int $checks = 0;

            public function checkStatus(string $transportName): CooldownStatus
            {
                return 3 >= ++$this->checks ? CooldownStatus::indefinite() : CooldownStatus::inactive();
            }

            public function recordSuccess(string $transportName, Envelope $envelope): void
            {
            }

            public function recordFailure(string $transportName, ?Envelope $envelope, \Throwable $throwable): void
            {
            }
        };

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $clock = new MockClock('2026-10-14 10:00:00+00:00');
        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, null, null, $clock, null, ['transport' => $cooldownStrategy]);
        $worker->run(['sleep' => 1000000]);

        // one regular idle sleep for each of the three checks that found the transport cooling down
        $this->assertEquals(new \DateTimeImmutable('2026-10-14 10:00:03+00:00'), $clock->now());
        $this->assertSame(1, $receiver->getAcknowledgeCount());
    }

    public function testWorkerStoppingWhenEmptyTreatsATransportCoolingDownAsEmpty()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('fails'))],
            [new Envelope(new DummyMessage('waits for the next run'))],
        ]);

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new \RuntimeException('Failure.'));

        $logs = [];
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('info')->willReturnCallback(static function (string $message, array $context = []) use (&$logs) {
            $logs[] = [$message, $context];
        });

        $clock = new MockClock('2026-10-14 10:00:00+00:00');
        $cooldownStrategy = new MultiplierCooldownStrategy(1, 10000, 2, 60000, 0, null, $clock);
        $worker = new Worker(['transport' => $receiver], $bus, null, $logger, null, $clock, null, ['transport' => $cooldownStrategy]);
        $worker->run(['stop_when_empty' => true]);

        $this->assertEquals(new \DateTimeImmutable('2026-10-14 10:00:00+00:00'), $clock->now());
        $this->assertCount(1, $receiver->getFetchSizes());
        $this->assertSame([
            ['Starting worker.', ['transport_names' => ['transport']]],
            ['Worker stopped because no message was available outside of the transports that are cooling down: {transports}', ['transports' => 'transport']],
        ], $logs);
    }

    public function testWorkerSkipsATransportWhileFetchingFromItFails()
    {
        $failure = new TransportException('Connection refused.');
        $receiver = new DummyReceiver([
            $failure,
            $failure,
            $failure,
            [new Envelope(new DummyMessage('Hello'))],
        ]);

        $cooldownEvents = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $dispatcher->addListener(WorkerTransportCoolingDownEvent::class, static function (WorkerTransportCoolingDownEvent $event) use (&$cooldownEvents) {
            $cooldownEvents[] = [$event->getTransportName(), $event->getStatus()->getEnd()];
        });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(3))
            ->method('warning')
            ->with('Fetching messages from transport {transport} failed: {error}', ['transport' => 'transport', 'error' => 'Connection refused.', 'exception' => $failure]);

        $clock = new MockClock('2026-10-14 10:00:00+00:00');
        $cooldownStrategy = new MultiplierCooldownStrategy(2, 10000, 2, 60000, 0, null, $clock);
        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, $logger, null, $clock, null, ['transport' => $cooldownStrategy]);
        $worker->run(['sleep' => 1000000]);

        // fetches at 10:00:00 and 10:00:01, a cooldown of 10s, a fetch at 10:00:11, a cooldown of 20s
        $this->assertEquals(new \DateTimeImmutable('2026-10-14 10:00:31+00:00'), $clock->now());
        $this->assertCount(4, $receiver->getFetchSizes());
        $this->assertSame(1, $receiver->getAcknowledgeCount());

        $this->assertEquals([
            ['transport', new \DateTimeImmutable('2026-10-14 10:00:11+00:00')],
            ['transport', new \DateTimeImmutable('2026-10-14 10:00:31+00:00')],
        ], $cooldownEvents);
    }

    public function testWorkerKeepsConsumingOtherTransportsWhileFetchingFromOneFails()
    {
        $failingReceiver = new DummyReceiver([
            new TransportException('Connection refused.'),
            new TransportException('Connection refused.'),
        ]);
        $otherReceiver = new DummyReceiver([
            [new Envelope(new DummyMessage('other 1'))],
            [new Envelope(new DummyMessage('other 2'))],
            [new Envelope(new DummyMessage('other 3'))],
        ]);

        $cooldownEvents = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(3));
        $dispatcher->addListener(WorkerTransportCoolingDownEvent::class, static function (WorkerTransportCoolingDownEvent $event) use (&$cooldownEvents) {
            $cooldownEvents[] = [$event->getTransportName(), $event->getStatus()->getEnd()];
        });

        $clock = new MockClock('2026-10-14 10:00:00+00:00');
        $cooldownStrategy = new MultiplierCooldownStrategy(2, 10000, 2, 60000, 0, null, $clock);
        $worker = new Worker(['failing' => $failingReceiver, 'other' => $otherReceiver], new MessageBus(), $dispatcher, null, null, $clock, null, ['failing' => $cooldownStrategy]);
        $worker->run();

        $this->assertEquals(new \DateTimeImmutable('2026-10-14 10:00:00+00:00'), $clock->now());
        $this->assertCount(2, $failingReceiver->getFetchSizes());
        $this->assertSame(3, $otherReceiver->getAcknowledgeCount());
        $this->assertEquals([['failing', new \DateTimeImmutable('2026-10-14 10:00:10+00:00')]], $cooldownEvents);
    }

    public function testWorkerStopsWhenFetchingFailsFromATransportWithoutCooldownStrategy()
    {
        $exception = new TransportException('Connection refused.');
        $failingReceiver = new DummyReceiver([$exception]);
        $otherReceiver = new DummyReceiver([[new Envelope(new DummyMessage('other'))]]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $clock = new MockClock('2026-10-14 10:00:00+00:00');
        $cooldownStrategy = new MultiplierCooldownStrategy(1, 1000, 2, 60000, 0, null, $clock);
        $worker = new Worker(['failing' => $failingReceiver, 'other' => $otherReceiver], new MessageBus(), $dispatcher, null, null, $clock, null, ['other' => $cooldownStrategy]);
        $failure = null;

        try {
            $worker->run();
        } catch (TransportException $failure) {
        }

        $this->assertSame($exception, $failure);
        $this->assertSame(0, $otherReceiver->getAcknowledgeCount());
    }

    public function testWorkerReportsTheFetchFailuresOfLazyReceiversToTheCooldownStrategy()
    {
        $receiver = new DummyQueueReceiver([
            (static function () {
                yield new Envelope(new DummyMessage('first'));

                throw new TransportException('Connection lost.');
            })(),
            [new Envelope(new DummyMessage('second'))],
        ]);

        $cooldownStrategy = new class implements CooldownStrategyInterface {
            public array $calls = [];

            public function checkStatus(string $transportName): CooldownStatus
            {
                $this->calls[] = ['checkStatus', $transportName];

                return CooldownStatus::inactive();
            }

            public function recordSuccess(string $transportName, Envelope $envelope): void
            {
                $this->calls[] = ['recordSuccess', $transportName, $envelope->getMessage()->getMessage()];
            }

            public function recordFailure(string $transportName, ?Envelope $envelope, \Throwable $throwable): void
            {
                $this->calls[] = ['recordFailure', $transportName, $envelope, $throwable->getMessage()];
            }
        };

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(2));

        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, null, null, new MockClock(), null, ['transport' => $cooldownStrategy]);
        $worker->run(['queues' => ['foo'], 'fetch_size' => 2]);

        $this->assertSame([
            ['checkStatus', 'transport'],
            ['recordSuccess', 'transport', 'first'],
            ['recordFailure', 'transport', null, 'Connection lost.'],
            ['checkStatus', 'transport'],
            ['recordSuccess', 'transport', 'second'],
        ], $cooldownStrategy->calls);
        $this->assertSame([['foo'], ['foo']], $receiver->queueNames);
    }

    public function testWorkerStoppingWhenEmptyTreatsATransportFailingToFetchAsEmpty()
    {
        $receiver = new DummyReceiver([
            new TransportException('Connection refused.'),
            [new Envelope(new DummyMessage('waits for the next run'))],
        ]);

        $clock = new MockClock('2026-10-14 10:00:00+00:00');
        $cooldownStrategy = new MultiplierCooldownStrategy(5, 1000, 2, 60000, 0, null, $clock);
        $worker = new Worker(['transport' => $receiver], new MessageBus(), null, null, null, $clock, null, ['transport' => $cooldownStrategy]);
        $worker->run(['stop_when_empty' => true]);

        $this->assertCount(1, $receiver->getFetchSizes());
        $this->assertSame(0, $receiver->getAcknowledgeCount());
    }

    public function testWorkerShouldLogOnStart()
    {
        $logs = [];
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('info')->willReturnCallback(static function (string $message, array $context = []) use (&$logs) {
            $logs[] = [$message, $context];
        });

        $worker = new Worker(['transport1' => new DummyReceiver([]), 'transport2' => new DummyReceiver([])], new MessageBus(), null, $logger, null, new MockClock());
        $worker->run(['stop_when_empty' => true]);

        $this->assertSame([
            ['Starting worker.', ['transport_names' => ['transport1', 'transport2']]],
            ['Worker stopped because no message was available', []],
        ], $logs);
    }

    public function testWorkerShouldLogOnStop()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('Stopping worker.');
        $worker = new Worker([], new MessageBus(), new EventDispatcher(), $logger, clock: new MockClock());

        $worker->stop();
    }

    public function testBatchProcessing()
    {
        $expectedMessages = [
            new DummyMessage('Hey'),
            new DummyMessage('Bob'),
        ];

        $receiver = new DummyReceiver([
            [new Envelope($expectedMessages[0])],
            [new Envelope($expectedMessages[1])],
        ]);

        $handler = new DummyBatchHandler();

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]));

        $bus = new MessageBus([$middleware]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, function (WorkerRunningEvent $event) use ($receiver) {
            static $i = 0;
            if (1 < ++$i) {
                $event->getWorker()->stop();
                $this->assertSame(2, $receiver->getAcknowledgeCount());
            } else {
                $this->assertSame(0, $receiver->getAcknowledgeCount());
            }
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, clock: new MockClock());
        $worker->run();

        $this->assertSame($expectedMessages, $handler->processedMessages);
    }

    public function testCompletedBatchIsNotRedispatchedWhileWorkerIsIdle()
    {
        $expectedMessages = [];
        $receivedEnvelopes = [];
        for ($i = 1; $i <= 10; ++$i) {
            $message = new DummyMessage('message'.$i);
            $expectedMessages[] = $message;
            $receivedEnvelopes[] = [new Envelope($message)];
        }

        $receiver = new DummyReceiver($receivedEnvelopes);
        $handler = new DummyBatchHandler(batchSize: 10);
        $clock = new MockClock();
        $bus = new TraceableMessageBus(new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([
                DummyMessage::class => [new HandlerDescriptor($handler)],
            ]), clock: $clock),
        ]));

        $worker = new Worker(['transport' => $receiver], $bus, clock: $clock);
        $worker->run(['time_limit' => 30]);

        $this->assertSame($expectedMessages, $handler->processedMessages);
        $this->assertSame(10, $receiver->getAcknowledgeCount());
        $this->assertCount(10, $bus->getDispatchedMessages());
    }

    public function testFlushFailureDoesNotRejectAlreadyAckedBatchMessage()
    {
        $expectedMessages = [
            new DummyMessage('Hey'),
        ];

        $receiver = new DummyReceiver([
            [new Envelope($expectedMessages[0])],
            [],
            [],
        ]);

        $handler = new DummyBatchHandler();

        $clock = new MockClock();
        $throwAfterFlush = new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $envelope = $stack->next()->handle($envelope, $stack);

                if ($envelope->last(FlushBatchHandlersStamp::class)) {
                    throw new \RuntimeException('Flush dispatch failed.');
                }

                return $envelope;
            }
        };
        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]), clock: $clock);

        $bus = new MessageBus([$throwAfterFlush, $middleware]);
        $clock = new MockClock();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) use ($clock) {
            static $i = 0;
            if (1 === ++$i) {
                $clock->sleep(30);
            } else {
                $event->getWorker()->stop();
            }
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, clock: $clock);
        $worker->run();

        $this->assertSame($expectedMessages, $handler->processedMessages);
        $this->assertSame(1, $receiver->getAcknowledgeCount());
        $this->assertSame(0, $receiver->getRejectCount());
    }

    public function testFlushBatchOnIdle()
    {
        $expectedMessages = [
            new DummyMessage('Hey'),
        ];

        $receiver = new DummyReceiver([
            [new Envelope($expectedMessages[0])],
            [],
            [],
        ]);

        $handler = new DummyBatchHandler();

        $clock = new MockClock();
        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]), clock: $clock);

        $bus = new MessageBus([$middleware]);
        $clock = new MockClock();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, function (WorkerRunningEvent $event) use ($receiver, $clock) {
            static $i = 0;
            if (1 === ++$i) {
                $this->assertSame(0, $receiver->getAcknowledgeCount());
                $clock->sleep(30);
            } elseif (2 === $i) {
                $event->getWorker()->stop();
                $this->assertSame(1, $receiver->getAcknowledgeCount());
            } else {
                $this->assertSame(0, $receiver->getAcknowledgeCount());
                $clock->sleep(1);
            }
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, clock: $clock);
        $worker->run();

        $this->assertSame($expectedMessages, $handler->processedMessages);
    }

    public function testFlushBatchWithIdleTimeout()
    {
        $expectedMessages = [
            new DummyMessage('Hey'),
            new DummyMessage('Bob'),
            new DummyMessage('Hello'),
            new DummyMessage('John'),
        ];

        $receiver = new DummyReceiver([
            [new Envelope($expectedMessages[0])],
            [new Envelope($expectedMessages[1])],
            [new Envelope($expectedMessages[2])],
            [new Envelope($expectedMessages[3])],
        ]);

        $handler = new DummyBatchHandler(batchSize: 5, idleTimeout: 3);

        $clock = new MockClock();
        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]), clock: $clock);

        $bus = new MessageBus([$middleware]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, function (WorkerRunningEvent $event) use ($receiver, $clock) {
            static $i = 0;
            if (6 < ++$i) { // 6 seconds = 4 messages + 3 idle timeout - 1 sec that overlaps (last message)
                $this->assertSame(4, $receiver->getAcknowledgeCount());
                $event->getWorker()->stop();
            } else {
                $this->assertSame(0, $receiver->getAcknowledgeCount());
                $clock->sleep(1);
            }
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, clock: $clock);
        $worker->run();

        $this->assertSame($expectedMessages, $handler->processedMessages);
    }

    public function testFlushBatchWithoutIdleTimeout()
    {
        $expectedMessages = [
            new DummyMessage('Hey'),
        ];

        $receiver = new DummyReceiver([
            [new Envelope($expectedMessages[0])],
            [],
        ]);

        $handler = new DummyBatchHandler(idleTimeout: null);

        $clock = new MockClock();
        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]), clock: $clock);

        $bus = new MessageBus([$middleware]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, function (WorkerRunningEvent $event) use ($receiver, $clock) {
            static $i = 0;
            if (2 < ++$i) {
                $event->getWorker()->stop();
            }
            $this->assertSame(0, $receiver->getAcknowledgeCount());
            $clock->sleep(1);
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, clock: $clock);
        $worker->run();

        $this->assertSame(1, $receiver->getAcknowledgeCount());

        $this->assertSame($expectedMessages, $handler->processedMessages);
    }

    public function testFlushBatchOnStop()
    {
        $expectedMessages = [
            new DummyMessage('Hey'),
        ];

        $receiver = new DummyReceiver([
            [new Envelope($expectedMessages[0])],
        ]);

        $handler = new DummyBatchHandler();

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]));

        $bus = new MessageBus([$middleware]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, function (WorkerRunningEvent $event) use ($receiver) {
            $event->getWorker()->stop();
            $this->assertSame(0, $receiver->getAcknowledgeCount());
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, clock: new MockClock());
        $worker->run();

        $this->assertSame($expectedMessages, $handler->processedMessages);
    }

    public function testFlushMultipleBatchOnStop()
    {
        $expectedMessages = [
            new DummyMessage('Hey'),
        ];

        $secondHandlerExpectedMessages = [
            new SecondHandlerDummyMessage('Ho'),
        ];

        $receiver = new DummyReceiver([
            [new Envelope($expectedMessages[0])],
        ]);

        $secondHandlerReceiver = new DummyReceiver([
            [new Envelope($secondHandlerExpectedMessages[0])],
        ]);

        $handler = new DummyBatchHandler();
        $secondHandler = new SecondDummyBatchHandler();

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
            SecondHandlerDummyMessage::class => [new HandlerDescriptor($secondHandler)],
        ]));

        $bus = new MessageBus([$middleware]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, function (WorkerRunningEvent $event) use ($receiver, $secondHandlerReceiver) {
            static $i = 0;
            if (1 < ++$i) {
                $event->getWorker()->stop();
            }

            $this->assertSame(0, $receiver->getAcknowledgeCount());
            $this->assertSame(0, $secondHandlerReceiver->getAcknowledgeCount());
        });

        $worker = new Worker(['first' => $receiver, 'second' => $secondHandlerReceiver], $bus, $dispatcher, clock: new MockClock());
        $worker->run();

        $this->assertSame($expectedMessages, $handler->processedMessages);
        $this->assertSame($secondHandlerExpectedMessages, $secondHandler->processedMessages);
    }

    public function testBatchFlushOnInactivityWhileWorkerIsBusy()
    {
        $batchHandler = new LargeBatchHandler();

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($batchHandler)],
            SecondHandlerDummyMessage::class => [new HandlerDescriptor(static function (SecondHandlerDummyMessage $message) {})],
        ]));

        $bus = new MessageBus([$middleware]);
        $clock = new MockClock('2024-01-01 00:00:00+00:00');

        // 1 batch message, then 34 non-batch messages keeping the worker busy.
        // The batch handler requires 100 messages to flush on its own,
        // so shouldFlush() never triggers. The worker is never idle either.
        $envelopes = [[new Envelope(new DummyMessage('batch'))]];
        for ($i = 0; $i < 34; ++$i) {
            $envelopes[] = [new Envelope(new SecondHandlerDummyMessage('regular'.$i))];
        }
        $receiver = new DummyReceiver($envelopes);

        $loopCount = 0;
        $flushedAtLoop = null;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) use ($clock, &$loopCount, &$flushedAtLoop, $batchHandler) {
            ++$loopCount;
            $clock->sleep(1);

            if ($batchHandler->processedMessages) {
                $flushedAtLoop ??= $loopCount;
            }

            if (43 <= $loopCount) {
                $event->getWorker()->stop();
            }
        });

        $worker = new Worker(['transport' => $receiver], $bus, $dispatcher, clock: $clock);
        $worker->run();

        // The batch message was received at t=0, then only non-batch messages arrived.
        // After 30s of batch inactivity, the worker should force-flush.
        $this->assertNotNull($flushedAtLoop, 'Batch should have been flushed after inactivity timeout');
        $this->assertGreaterThanOrEqual(30, $flushedAtLoop);
        $this->assertLessThanOrEqual(33, $flushedAtLoop);
        $this->assertSame(['batch'], array_map(static fn ($m) => $m->getMessage(), $batchHandler->processedMessages));
    }

    public function testFlushRemovesNoAutoAckStampOnException()
    {
        $envelope = new Envelope(new DummyMessage('Test'));
        $receiver = new DummyReceiver([[$envelope]]);

        $bus = new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $envelope = Envelope::wrap($message, $stamps);
                if ($envelope->last(FlushBatchHandlersStamp::class)) {
                    throw new \RuntimeException('Flush failed');
                }

                return $envelope;
            }
        };

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) {
            static $calls = 0;
            if (++$calls >= 2) {
                $event->getWorker()->stop();
            }
        });

        $worker = new Worker(['transport' => $receiver], $bus, $dispatcher, clock: new MockClock());

        $dummyHandler = new DummyBatchHandler();
        $envelopeWithNoAutoAck = $envelope->with(new NoAutoAckStamp(new HandlerDescriptor($dummyHandler)));
        $acked = false;
        $unacks = new DeferredBatchMessageQueue();
        $unacks->add($dummyHandler, 'transport', $envelopeWithNoAutoAck, $acked, 0.0);
        (new \ReflectionProperty($worker, 'unacks'))->setValue($worker, $unacks);

        $worker->run();

        $this->assertSame(1, $receiver->getRejectCount());
        $rejectedEnvelopes = $receiver->getRejectedEnvelopes();
        $this->assertCount(1, $rejectedEnvelopes);
        $rejectedEnvelope = $rejectedEnvelopes[0];
        $this->assertNull($rejectedEnvelope->last(NoAutoAckStamp::class));
    }

    public function testGcCollectCyclesIsCalledOnIdleWorker()
    {
        $apiMessage = new DummyMessage('API');

        $receiver = new DummyReceiver([[new Envelope($apiMessage)]]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ResetMemoryUsageListener());
        $before = 0;
        $dispatcher->addListener(WorkerRunningEvent::class, function (WorkerRunningEvent $event) use (&$before) {
            static $i = 0;

            $after = gc_status()['runs'];
            if (0 === $i) {
                $this->assertFalse($event->isWorkerIdle());
                $this->assertSame(0, $after - $before);
            } elseif (1 === $i) {
                $this->assertTrue($event->isWorkerIdle());
                $this->assertSame(1, $after - $before);
            } elseif (3 === $i) {
                // Wait a few idle phases before stopping.
                $this->assertSame(1, $after - $before);
                $event->getWorker()->stop();
            }

            ++$i;
        }, \PHP_INT_MIN);

        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher);

        gc_collect_cycles();
        $before = gc_status()['runs'];

        $worker->run([
            'sleep' => 0,
        ]);
    }

    public function testMemoryUsageIsResetOnMessageHandle()
    {
        $apiMessage = new DummyMessage('API');

        $receiver = new DummyReceiver([[new Envelope($apiMessage)]]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ResetMemoryUsageListener());
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        // Allocate and deallocate 4 MB. The use of random_int() is to
        // prevent compile-time optimization.
        $memory = str_repeat(random_int(0, 1), 4 * 1024 * 1024);
        unset($memory);

        $before = memory_get_peak_usage();

        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher);
        $worker->run();

        // This should be roughly 4 MB smaller than $before.
        $after = memory_get_peak_usage();

        $this->assertTrue($after < $before);
    }

    #[RequiresPhpExtension('pcntl')]
    public function testKeepalive()
    {
        ClockMock::withClockMock(false);

        $expectedEnvelopes = [
            new Envelope(new DummyMessage('Hey')),
            new Envelope(new DummyMessage('Bob')),
        ];

        $receiver = new DummyKeepaliveReceiver([
            [$expectedEnvelopes[0]],
            [$expectedEnvelopes[1]],
        ]);

        $handler = new DummyBatchHandler(3);

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]));

        $bus = new MessageBus([$middleware]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, function (WorkerRunningEvent $event) use ($receiver) {
            static $i = 0;
            if (1 < ++$i) {
                $event->getWorker()->stop();
                $this->assertSame(2, $receiver->getAcknowledgeCount());
            } else {
                $this->assertSame(0, $receiver->getAcknowledgeCount());
            }
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, clock: new MockClock());

        try {
            $oldAsync = pcntl_async_signals(true);
            pcntl_signal(\SIGALRM, static fn () => $worker->keepalive(2));
            pcntl_alarm(2);

            $worker->run();
        } finally {
            pcntl_async_signals($oldAsync);
            pcntl_signal(\SIGALRM, \SIG_DFL);
        }

        $this->assertCount(2, $receiver->keepaliveEnvelopes);
        $this->assertSame($expectedEnvelopes, $receiver->keepaliveEnvelopes);

        $receiver->keepaliveEnvelopes = [];
        $worker->keepalive(2);

        $this->assertCount(0, $receiver->keepaliveEnvelopes);
    }

    #[RequiresPhpExtension('pcntl')]
    #[RequiresPhpExtension('posix')]
    public function testAFailingKeepaliveDoesNotStopTheWorker()
    {
        ClockMock::withClockMock(false);

        $receiver = new ThrowingKeepaliveReceiver([
            [new Envelope(new DummyMessage('Hey'))],
            [new Envelope(new DummyMessage('Bob'))],
        ]);
        // the alarm lands while the worker polls, with the first message still held by the batch
        $receiver->raiseAlarmOnGet = true;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('warning')->with('Keepalive request failed: {error}');

        $worker = new Worker([$receiver], $this->createBatchBus(), $this->createStoppingDispatcher(2), $logger, clock: new MockClock());

        $this->runWithKeepaliveOnAlarm($worker);

        $this->assertSame(2, $receiver->getAcknowledgeCount());
        $this->assertSame(0, $receiver->getRejectCount());
    }

    #[RequiresPhpExtension('pcntl')]
    #[RequiresPhpExtension('posix')]
    public function testAFailingKeepaliveIsNotChargedToTheMessageBeingHandled()
    {
        ClockMock::withClockMock(false);

        $receiver = new ThrowingKeepaliveReceiver([[new Envelope(new DummyMessage('Hey'))]]);

        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor(static function () {
                // the alarm lands in the middle of the handler, which is what keepalive is for
                posix_kill(posix_getpid(), \SIGALRM);
                usleep(1000);
            })],
        ]))]);

        $failures = [];
        $dispatcher = $this->createStoppingDispatcher(1);
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$failures) {
            $failures[] = $event->getThrowable()->getMessage();
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, clock: new MockClock());

        $this->runWithKeepaliveOnAlarm($worker);

        $this->assertSame([], $failures);
        $this->assertSame(1, $receiver->getAcknowledgeCount());
        $this->assertSame(0, $receiver->getRejectCount());
    }

    #[RequiresPhpExtension('pcntl')]
    #[RequiresPhpExtension('posix')]
    public function testAFailingKeepaliveIsNotChargedToTheBatchBeingFlushed()
    {
        ClockMock::withClockMock(false);

        $receiver = new ThrowingKeepaliveReceiver([
            [new Envelope(new DummyMessage('Hey'))],
            [new Envelope(new DummyMessage('Bob'))],
        ]);

        // the batch takes long enough for the alarm to land inside its flush
        $worker = new Worker([$receiver], $this->createBatchBus(3), $this->createStoppingDispatcher(2), clock: new MockClock());

        $this->runWithKeepaliveOnAlarm($worker, 2);

        $this->assertSame(2, $receiver->getAcknowledgeCount());
        $this->assertSame(0, $receiver->getRejectCount());
    }

    public function testAFailingAckIsNotReportedAsAHandlingFailure()
    {
        $receiver = new ThrowingAckReceiver([[new Envelope(new DummyMessage('Hey'))]]);

        $handlerCalls = 0;
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor(static function () use (&$handlerCalls) {
                ++$handlerCalls;
            })],
        ]))]);

        $events = [];
        $dispatcher = $this->createStoppingDispatcher(1);
        $dispatcher->addListener(WorkerMessageHandledEvent::class, static function () use (&$events) {
            $events[] = WorkerMessageHandledEvent::class;
        });
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function () use (&$events) {
            $events[] = WorkerMessageFailedEvent::class;
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, clock: new MockClock());
        $failure = null;

        try {
            $worker->run();
        } catch (TransportException $failure) {
        }

        $this->assertInstanceOf(TransportException::class, $failure);
        $this->assertSame('Could not acknowledge the message.', $failure->getMessage());

        $this->assertSame(1, $handlerCalls);
        $this->assertSame([WorkerMessageHandledEvent::class], $events);
        $this->assertSame(1, $receiver->getAcknowledgeCount());
        $this->assertSame(0, $receiver->getRejectCount());
    }

    private function createBatchBus(?int $delay = null): MessageBus
    {
        return new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor(new DummyBatchHandler($delay))],
        ]))]);
    }

    private function createStoppingDispatcher(int $afterRunningEvents): EventDispatcher
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) use ($afterRunningEvents) {
            static $i = 0;
            if ($afterRunningEvents <= ++$i) {
                $event->getWorker()->stop();
            }
        });

        return $dispatcher;
    }

    private function runWithKeepaliveOnAlarm(Worker $worker, int $alarm = 0): void
    {
        $previousHandler = pcntl_signal_get_handler(\SIGALRM);
        $previousAsync = pcntl_async_signals(true);
        pcntl_signal(\SIGALRM, static fn () => $worker->keepalive(2));

        if ($alarm) {
            pcntl_alarm($alarm);
        }

        try {
            $worker->run();
        } finally {
            pcntl_alarm(0);
            pcntl_async_signals($previousAsync);
            pcntl_signal(\SIGALRM, $previousHandler);
        }
    }
}

class PausingExecutionStrategy implements MessageExecutionStrategyInterface
{
    public array $executed = [];
    private array $inFlight = [];

    public function execute(Envelope $envelope, string $transportName, callable $onHandled): void
    {
        $this->executed[] = $envelope->getMessage()->getMessage();
        $this->inFlight[] = [$envelope, $transportName];
    }

    public function shouldPauseConsumption(): bool
    {
        return (bool) $this->inFlight;
    }

    public function wait(callable $onHandled, ?float $timeout = null): bool
    {
        return $this->flush($onHandled);
    }

    public function flush(callable $onHandled, bool|float $force = false): bool
    {
        if (!$this->inFlight) {
            return false;
        }

        foreach ($this->inFlight as [$envelope, $transportName]) {
            $acked = false;
            $onHandled($envelope, $transportName, $acked, null);
        }
        $this->inFlight = [];

        return true;
    }

    public function shutdown(): void
    {
    }
}

class AppendableReceiver implements ReceiverInterface
{
    public array $acked = [];

    public function __construct(
        public array $queue = [],
    ) {
    }

    public function get(/* int $fetchSize = 1 */): iterable
    {
        return array_splice($this->queue, 0, \func_num_args() > 0 ? func_get_arg(0) : 1);
    }

    public function ack(Envelope $envelope): void
    {
        $this->acked[] = $envelope->getMessage()->getMessage();
    }

    public function reject(Envelope $envelope): void
    {
    }
}

class FollowUpBatchHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    public function __construct(
        private AppendableReceiver $receiver,
    ) {
    }

    public function __invoke(DummyMessage $message, ?Acknowledger $ack = null)
    {
        return $this->handle($message, $ack);
    }

    private function getIdleTimeout(): ?int
    {
        return null;
    }

    private function process(array $jobs): void
    {
        foreach ($jobs as [$job, $ack]) {
            if ('first' === $job->getMessage()) {
                $this->receiver->queue[] = new Envelope(new DummyMessage('follow-up'));
            }

            $ack->ack();
        }
    }
}

class DeferringExecutionStrategy implements MessageExecutionStrategyInterface
{
    private array $inFlight = [];

    public function __construct(
        private \Closure $handler,
    ) {
    }

    public function execute(Envelope $envelope, string $transportName, callable $onHandled): void
    {
        $this->inFlight[] = [$envelope, $transportName];
    }

    public function shouldPauseConsumption(): bool
    {
        return false;
    }

    public function wait(callable $onHandled, ?float $timeout = null): bool
    {
        return false;
    }

    public function flush(callable $onHandled, bool|float $force = false): bool
    {
        if (true !== $force || !$this->inFlight) {
            return false;
        }

        foreach (array_splice($this->inFlight, 0) as [$envelope, $transportName]) {
            ($this->handler)($envelope);
            $acked = false;
            $onHandled($envelope, $transportName, $acked, null);
        }

        return true;
    }

    public function shutdown(): void
    {
    }
}

class ThrowingKeepaliveReceiver extends DummyReceiver implements KeepaliveReceiverInterface
{
    public bool $raiseAlarmOnGet = false;

    public function get(): iterable
    {
        $envelopes = parent::get();

        if ($this->raiseAlarmOnGet) {
            posix_kill(posix_getpid(), \SIGALRM);
            usleep(1000);
        }

        return $envelopes;
    }

    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        throw new TransportException('Connection to the broker was lost.');
    }
}

class ThrowingAckReceiver extends DummyReceiver
{
    public function ack(Envelope $envelope): void
    {
        parent::ack($envelope);

        throw new TransportException('Could not acknowledge the message.');
    }
}

class DummyQueueReceiver extends DummyReceiver implements QueueReceiverInterface
{
    public array $queueNames = [];
    public array $getFromQueuesFetchSizes = [];

    /**
     * @param int $fetchSize
     */
    public function getFromQueues(array $queueNames/* , int $fetchSize = 1 */): iterable
    {
        $this->queueNames[] = $queueNames;
        $this->getFromQueuesFetchSizes[] = 1 < \func_num_args() ? func_get_arg(1) : 1;

        return $this->get(...\func_num_args() > 1 ? [func_get_arg(1)] : []);
    }
}

class DummyKeepaliveReceiver extends DummyReceiver implements KeepaliveReceiverInterface
{
    public array $keepaliveEnvelopes = [];

    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        $this->keepaliveEnvelopes[] = $envelope;
    }
}

class DummyBatchHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    public array $processedMessages;

    public function __construct(
        private ?int $delay = null,
        private int $batchSize = 2,
        private ?int $idleTimeout = 1,
    ) {
    }

    public function __invoke(DummyMessage $message, ?Acknowledger $ack = null)
    {
        return $this->handle($message, $ack);
    }

    private function getBatchSize(): int
    {
        return $this->batchSize;
    }

    private function getIdleTimeout(): ?int
    {
        return $this->idleTimeout;
    }

    private function process(array $jobs): void
    {
        $this->processedMessages = array_column($jobs, 0);

        if (null !== $this->delay) {
            for ($i = 0; $i < $this->delay; ++$i) {
                sleep(1);
            }
        }

        foreach ($jobs as [$job, $ack]) {
            $ack->ack($job);
        }
    }
}

class SecondDummyBatchHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    public array $processedMessages;

    public function __invoke(SecondHandlerDummyMessage $message, ?Acknowledger $ack = null)
    {
        return $this->handle($message, $ack);
    }

    private function getBatchSize(): int
    {
        return 5;
    }

    private function process(array $jobs): void
    {
        $this->processedMessages = array_column($jobs, 0);

        foreach ($jobs as [$job, $ack]) {
            $ack->ack($job);
        }
    }
}

class LargeBatchHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    public array $processedMessages = [];

    public function __invoke(DummyMessage $message, ?Acknowledger $ack = null)
    {
        return $this->handle($message, $ack);
    }

    private function shouldFlush(): bool
    {
        return 100 <= \count($this->jobs);
    }

    private function process(array $jobs): void
    {
        $this->processedMessages = array_merge($this->processedMessages, array_column($jobs, 0));

        foreach ($jobs as [$job, $ack]) {
            $ack->ack($job);
        }
    }
}

class SecondHandlerDummyMessage implements DummyMessageInterface
{
    private string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
