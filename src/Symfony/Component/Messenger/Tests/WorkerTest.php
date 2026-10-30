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
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRateLimitedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RejectRedeliveredMessageException;
use Symfony\Component\Messenger\Exception\RuntimeException;
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
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\FlushBatchHandlersStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyReceiver;
use Symfony\Component\Messenger\Tests\Fixtures\ResettableDummyReceiver;
use Symfony\Component\Messenger\Transport\Receiver\QueueReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Reservation;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

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

    public function testRetriedMessageIsAcknowledged()
    {
        $receiver = new DummyReceiver([[new Envelope(new DummyMessage('Hello'))]]);
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new \RuntimeException('Retry me.'));
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->once())->method('send')->willReturnArgument(0);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(new ServiceLocator(['transport' => static fn () => $sender]), new ServiceLocator(['transport' => static fn () => new MultiplierRetryStrategy()])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $worker = new Worker(['transport' => $receiver], $bus, $dispatcher, null, null, new MockClock());
        $worker->run();

        $this->assertSame(1, $receiver->getAcknowledgeCount());
        $this->assertSame(0, $receiver->getRejectCount());
    }

    public function testRedeliveredMessageIsRejectedBeforeBeingRetried()
    {
        $receiver = new DummyReceiver([[new Envelope(new DummyMessage('Hello'))]]);
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new RejectRedeliveredMessageException('Redelivered.'));
        $sender = $this->createMock(SenderInterface::class);
        $sender->expects($this->once())->method('send')->willReturnArgument(0);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(new ServiceLocator(['transport' => static fn () => $sender]), new ServiceLocator(['transport' => static fn () => new MultiplierRetryStrategy()])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $worker = new Worker(['transport' => $receiver], $bus, $dispatcher, null, null, new MockClock());
        $worker->run();

        $this->assertSame(0, $receiver->getAcknowledgeCount());
        $this->assertSame(1, $receiver->getRejectCount());
    }

    public function testFailedRetrySendDoesNotAcknowledgeMessage()
    {
        $receiver = new DummyReceiver([[new Envelope(new DummyMessage('Hello'))]]);
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new \RuntimeException('Retry me.'));
        $sender = $this->createStub(SenderInterface::class);
        $sender->method('send')->willThrowException(new \LogicException('Send failed.'));
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(new ServiceLocator(['transport' => static fn () => $sender]), new ServiceLocator(['transport' => static fn () => new MultiplierRetryStrategy()])));

        $worker = new Worker(['transport' => $receiver], $bus, $dispatcher, null, null, new MockClock());
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Send failed.');

        try {
            $worker->run();
        } finally {
            $this->assertSame(0, $receiver->getAcknowledgeCount());
            $this->assertSame(0, $receiver->getRejectCount());
        }
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
        $receiver = $this->createMock(QueueReceiverInterface::class);
        $receiver->expects($this->once())
            ->method('getFromQueues')
            ->with(['foo'])
            ->willReturn($envelope)
        ;
        $receiver->expects($this->never())
            ->method('get')
        ;

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $worker = new Worker(['transport' => $receiver], new MessageBus(), $dispatcher, clock: new MockClock());
        $worker->run(['queues' => ['foo']]);
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

    public function testBatchAckDoesNotHideTheFailureOfAnotherHandler()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('Hey'))],
            [new Envelope(new DummyMessage('Bob'))],
        ]);

        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [
                new HandlerDescriptor(new DummyBatchHandler()),
                static function (DummyMessage $message) {
                    if ('Bob' === $message->getMessage()) {
                        throw new \RuntimeException('Handler failed.');
                    }
                },
            ],
        ]))]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(2));

        $worker = new Worker([$receiver], $bus, $dispatcher, clock: new MockClock());
        $worker->run();

        $this->assertSame(0, $receiver->getAcknowledgeCount());
        $this->assertSame(['Hey', 'Bob'], array_map(static fn (Envelope $envelope) => $envelope->getMessage()->getMessage(), $receiver->getRejectedEnvelopes()));
    }

    public function testBatchWithAFailedMessageIsFlushedInADispatchOfItsOwn()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('Hey'))],
            [new Envelope(new DummyMessage('Bob'))],
        ]);

        $transaction = $this->createTransactionMiddleware();

        $batchHandler = $this->createBatchHandler(static function (array $jobs) use ($transaction) {
            foreach ($jobs as [$message, $ack]) {
                $transaction->pending[] = $message->getMessage();
                $ack->ack('batch result');
            }
        });

        $bus = new MessageBus([$transaction, new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [
                new HandlerDescriptor($batchHandler),
                static function (DummyMessage $message) {
                    if ('Bob' === $message->getMessage()) {
                        throw new \RuntimeException('Handler failed.');
                    }
                },
            ],
        ]))]);

        $failures = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(2));
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$failures) {
            $failures[] = $event->getEnvelope();
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, null, null, new MockClock());
        $worker->run();

        $this->assertSame(['Hey', 'Bob'], $transaction->committed);
        $this->assertSame(1, $receiver->getAcknowledgeCount());
        $this->assertSame(1, $receiver->getRejectCount());
        $this->assertCount(1, $failures);
        $this->assertSame(['batch result'], array_map(static fn (HandledStamp $stamp) => $stamp->getResult(), $failures[0]->all(HandledStamp::class)));
    }

    public function testBatchHandlerNackJoinsTheFailureOfTheMessage()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('Bob'))],
        ]);

        $processed = [];
        $batchHandler = $this->createBatchHandler(static function (array $jobs) use (&$processed) {
            foreach ($jobs as [$message, $ack]) {
                $processed[] = $message->getMessage();
                $ack->nack(new \RuntimeException('Batch failed.'));
            }
        });

        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [
                new HandlerDescriptor(static fn () => null, ['alias' => 'before']),
                new HandlerDescriptor($batchHandler),
                static fn () => throw new \RuntimeException('Handler failed.'),
            ],
        ]))]);

        $failures = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$failures) {
            $failures[] = $event->getThrowable();
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, null, null, new MockClock());
        $worker->run();

        $this->assertSame(['Bob'], $processed);
        $this->assertSame(0, $receiver->getAcknowledgeCount());
        $this->assertSame(1, $receiver->getRejectCount());
        $this->assertCount(1, $failures);
        $this->assertSame(['Handler failed.', 'Batch failed.'], array_map(static fn (\Throwable $e) => $e->getMessage(), array_values($failures[0]->getWrappedExceptions())));
        $this->assertSame(['Closure@before'], array_map(static fn (HandledStamp $stamp) => $stamp->getHandlerName(), $failures[0]->getEnvelope()->all(HandledStamp::class)));
    }

    public function testFailedFlushJoinsTheFailureOfTheMessage()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('Bob'))],
        ]);

        $bus = new class(new HandlerDescriptor(new DummyBatchHandler())) implements MessageBusInterface {
            public function __construct(
                private HandlerDescriptor $batchHandler,
            ) {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $envelope = Envelope::wrap($message, $stamps);

                if ($envelope->last(FlushBatchHandlersStamp::class)) {
                    throw new \RuntimeException('Flush failed.');
                }

                $envelope->last(AckStamp::class)->ack($envelope->with(new HandledStamp('rolled back', 'other batch handler')));

                throw new HandlerFailedException($envelope->with(new NoAutoAckStamp($this->batchHandler)), ['handler' => new \RuntimeException('Handler failed.')]);
            }
        };

        $failures = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$failures) {
            $failures[] = $event->getThrowable();
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, null, null, new MockClock());
        $worker->run();

        $this->assertSame(0, $receiver->getAcknowledgeCount());
        $this->assertSame(1, $receiver->getRejectCount());
        $this->assertCount(1, $failures);
        $this->assertSame(['Handler failed.', 'Flush failed.'], array_map(static fn (\Throwable $e) => $e->getMessage(), array_values($failures[0]->getWrappedExceptions())));
        $this->assertSame([], $failures[0]->getEnvelope()->all(HandledStamp::class));
    }

    public function testAcksOfABatchFlushedDuringAFailedDispatchBecomeFailures()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('Hey'))],
            [new Envelope(new DummyMessage('Joe'))],
            [new Envelope(new DummyMessage('Bob'))],
        ]);

        $transaction = $this->createTransactionMiddleware();

        $batchHandler = $this->createBatchHandler(static function (array $jobs) use ($transaction) {
            foreach ($jobs as [$message, $ack]) {
                $transaction->pending[] = $message->getMessage();

                if ('Joe' === $message->getMessage()) {
                    $ack->nack(new \RuntimeException('Batch failed.'));
                } else {
                    $ack->ack('batch result');
                }
            }
        });

        $bus = new MessageBus([$transaction, new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [
                new HandlerDescriptor($batchHandler),
                static function (DummyMessage $message) {
                    if ('Bob' === $message->getMessage()) {
                        throw new \RuntimeException('Handler failed.');
                    }
                },
            ],
        ]))]);

        $failures = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(3));
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$failures) {
            $failures[$event->getEnvelope()->getMessage()->getMessage()] = $event->getThrowable();
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, null, null, new MockClock());
        $worker->run();

        $this->assertSame([], $transaction->committed);
        $this->assertSame(0, $receiver->getAcknowledgeCount());
        $this->assertSame(3, $receiver->getRejectCount());
        $this->assertSame(['Hey', 'Joe', 'Bob'], array_keys($failures));

        $this->assertSame([], $failures['Hey']->getEnvelope()->all(HandledStamp::class));
        $this->assertStringContainsString('was flushed during the dispatch of another message, and that dispatch failed.', $failures['Hey']->getMessage());
        $this->assertSame(['Batch failed.'], array_map(static fn (\Throwable $e) => $e->getMessage(), array_values($failures['Joe']->getWrappedExceptions())));
    }

    public function testAcksSentDuringAFailedFlushBecomeFailures()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('Hey'))],
            [new Envelope(new SecondHandlerDummyMessage('Ho'))],
        ]);

        $throwAfterFlush = new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $envelope = $stack->next()->handle($envelope, $stack);

                if ($envelope->last(FlushBatchHandlersStamp::class) && $envelope->getMessage() instanceof SecondHandlerDummyMessage) {
                    throw new \RuntimeException('Flush dispatch failed.');
                }

                return $envelope;
            }
        };

        $bus = new MessageBus([$throwAfterFlush, new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor(new DummyBatchHandler())],
            SecondHandlerDummyMessage::class => [new HandlerDescriptor(new SecondDummyBatchHandler())],
        ]))]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(2));

        $worker = new Worker([$receiver], $bus, $dispatcher, null, null, new MockClock());
        $worker->run();

        $this->assertSame(1, $receiver->getAcknowledgeCount());
        $this->assertSame(['Ho'], array_map(static fn (Envelope $envelope) => $envelope->getMessage()->getMessage(), $receiver->getRejectedEnvelopes()));
    }

    public function testBatchFlushedForAFailedMessageRunsAgainWhenTheFlushFails()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('Hey'))],
            [new Envelope(new DummyMessage('Bob'))],
        ]);

        $throwAfterFirstFlush = new class implements MiddlewareInterface {
            private bool $thrown = false;

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $envelope = $stack->next()->handle($envelope, $stack);

                if (!$this->thrown && $envelope->last(FlushBatchHandlersStamp::class)) {
                    $this->thrown = true;

                    throw new \RuntimeException('Flush dispatch failed.');
                }

                return $envelope;
            }
        };

        $batchHandler = $this->createBatchHandler(static function (array $jobs) {
            foreach ($jobs as [, $ack]) {
                $ack->ack('batch result');
            }
        });

        $bus = new MessageBus([$throwAfterFirstFlush, new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [
                new HandlerDescriptor($batchHandler),
                static function (DummyMessage $message) {
                    if ('Bob' === $message->getMessage()) {
                        throw new \RuntimeException('Handler failed.');
                    }
                },
            ],
        ]))]);

        $failures = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(2));
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$failures) {
            $failures[$event->getEnvelope()->getMessage()->getMessage()] = $event->getThrowable();
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, null, null, new MockClock());
        $worker->run();

        $this->assertSame(0, $receiver->getAcknowledgeCount());
        $this->assertSame(['Hey', 'Bob'], array_keys($failures));
        $this->assertSame(['Handler failed.', 'Flush dispatch failed.'], array_map(static fn (\Throwable $e) => $e->getMessage(), array_values($failures['Bob']->getWrappedExceptions())));
        $this->assertSame([], $failures['Bob']->getEnvelope()->all(HandledStamp::class));
    }

    public function testBatchHandlerThatFailsToFlushRunsAgainOnRetry()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('Hey'))],
        ]);

        $batchHandler = $this->createBatchHandler(static function () {
            throw new \RuntimeException('Batch failed.');
        });

        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [
                new HandlerDescriptor($batchHandler),
                new HandlerDescriptor(static fn () => 'handler result', ['alias' => 'handler']),
            ],
        ]))]);

        $failures = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$failures) {
            $failures[] = $event->getThrowable();
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, null, null, new MockClock());
        $worker->run();

        $this->assertSame(0, $receiver->getAcknowledgeCount());
        $this->assertSame(1, $receiver->getRejectCount());
        $this->assertCount(1, $failures);
        $this->assertSame(['Batch failed.'], array_map(static fn (\Throwable $e) => $e->getMessage(), array_values($failures[0]->getWrappedExceptions())));
        $this->assertSame(['handler result'], array_map(static fn (HandledStamp $stamp) => $stamp->getResult(), $failures[0]->getEnvelope()->all(HandledStamp::class)));
    }

    public function testFailedFlushDropsTheHandledStampOfTheBatchHandlersThatFailed()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('Hey'))],
        ]);

        $bus = new class(new HandlerDescriptor(new DummyBatchHandler())) implements MessageBusInterface {
            public function __construct(
                private HandlerDescriptor $batchHandler,
            ) {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $envelope = Envelope::wrap($message, $stamps);

                if ($envelope->last(FlushBatchHandlersStamp::class)) {
                    throw new HandlerFailedException($envelope, [$this->batchHandler->getName() => new \RuntimeException('Flush failed.')]);
                }

                return $envelope->with(new HandledStamp('handler result', 'handler'), new NoAutoAckStamp($this->batchHandler), HandledStamp::fromDescriptor($this->batchHandler, 1));
            }
        };

        $failures = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event) use (&$failures) {
            $failures[] = $event->getThrowable();
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, null, null, new MockClock());
        $worker->run();

        $this->assertSame(1, $receiver->getRejectCount());
        $this->assertCount(1, $failures);
        $this->assertSame(['Flush failed.'], array_map(static fn (\Throwable $e) => $e->getMessage(), array_values($failures[0]->getWrappedExceptions())));
        $this->assertSame(['handler'], array_map(static fn (HandledStamp $stamp) => $stamp->getHandlerName(), $failures[0]->getEnvelope()->all(HandledStamp::class)));
        $this->assertSame(['handler'], array_map(static fn (HandledStamp $stamp) => $stamp->getHandlerName(), $receiver->getRejectedEnvelopes()[0]->all(HandledStamp::class)));
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

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]));

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
            }
        });

        $worker = new Worker([$receiver], $bus, $dispatcher, clock: $clock);
        $worker->run();

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
        $unacks = new \SplObjectStorage();
        $unacks[$dummyHandler] = [$envelopeWithNoAutoAck, 'transport', 0];
        (new \ReflectionProperty($worker, 'unacks'))->setValue($worker, $unacks);

        $worker->run();

        $this->assertSame(1, $receiver->getRejectCount());
        $rejectedEnvelopes = $receiver->getRejectedEnvelopes();
        $this->assertCount(1, $rejectedEnvelopes);
        $rejectedEnvelope = $rejectedEnvelopes[0];
        $this->assertNull($rejectedEnvelope->last(NoAutoAckStamp::class));
    }

    private function createTransactionMiddleware(): MiddlewareInterface
    {
        // like doctrine_transaction: rolls back on failure and strips the handled stamps
        return new class implements MiddlewareInterface {
            public array $pending = [];
            public array $committed = [];

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                try {
                    $envelope = $stack->next()->handle($envelope, $stack);
                    array_push($this->committed, ...$this->pending);

                    return $envelope;
                } catch (HandlerFailedException $e) {
                    throw new HandlerFailedException($e->getEnvelope()->withoutAll(HandledStamp::class), $e->getWrappedExceptions());
                } finally {
                    $this->pending = [];
                }
            }
        };
    }

    private function createBatchHandler(\Closure $process): BatchHandlerInterface
    {
        return new class($process) implements BatchHandlerInterface {
            use BatchHandlerTrait;

            public function __construct(
                private \Closure $process,
            ) {
            }

            public function __invoke(DummyMessage $message, ?Acknowledger $ack = null)
            {
                return $this->handle($message, $ack);
            }

            private function getBatchSize(): int
            {
                return 3;
            }

            private function process(array $jobs): void
            {
                ($this->process)($jobs);
            }
        };
    }
}

class DummyQueueReceiver extends DummyReceiver implements QueueReceiverInterface
{
    public function getFromQueues(array $queueNames): iterable
    {
        return $this->get();
    }
}

class DummyBatchHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    public array $processedMessages;

    public function __invoke(DummyMessage $message, ?Acknowledger $ack = null)
    {
        return $this->handle($message, $ack);
    }

    private function shouldFlush(): bool
    {
        return 2 <= \count($this->jobs);
    }

    private function process(array $jobs): void
    {
        $this->processedMessages = array_column($jobs, 0);

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

    private function shouldFlush(): bool
    {
        return 5 <= \count($this->jobs);
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
