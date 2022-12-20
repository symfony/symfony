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
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Receiver\QueueReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

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

        $bus = self::createMock(MessageBusInterface::class);
        $envelopes = [];

        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($envelope) use (&$envelopes) {
                return $envelopes[] = $envelope;
            });

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(2));

        $worker = new Worker(['transport' => $receiver], $bus, $dispatcher);
        $worker->run();

        self::assertSame($apiMessage, $envelopes[0]->getMessage());
        self::assertSame($ipaMessage, $envelopes[1]->getMessage());
        self::assertCount(1, $envelopes[0]->all(ReceivedStamp::class));
        self::assertCount(1, $envelopes[0]->all(ConsumedByWorkerStamp::class));
        self::assertSame('transport', $envelopes[0]->last(ReceivedStamp::class)->getTransportName());

        self::assertSame(2, $receiver->getAcknowledgeCount());
    }

    public function testHandlingErrorCausesReject()
    {
        $receiver = new DummyReceiver([
            [new Envelope(new DummyMessage('Hello'), [new SentStamp('Some\Sender', 'transport1')])],
        ]);

        $bus = self::createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new \InvalidArgumentException('Why not'));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $worker = new Worker(['transport1' => $receiver], $bus, $dispatcher);
        $worker->run();

        self::assertSame(1, $receiver->getRejectCount());
        self::assertSame(0, $receiver->getAcknowledgeCount());
    }

    public function testWorkerResetsConnectionIfReceiverIsResettable()
    {
        $resettableReceiver = new ResettableDummyReceiver([]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ResetServicesListener(new ServicesResetter(new \ArrayIterator([$resettableReceiver]), ['reset'])));

        $bus = self::createMock(MessageBusInterface::class);
        $worker = new Worker([$resettableReceiver], $bus, $dispatcher);
        $worker->stop();
        $worker->run();
        self::assertTrue($resettableReceiver->hasBeenReset());
    }

    public function testWorkerResetsTransportsIfResetServicesListenerIsCalled()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $resettableReceiver = new ResettableDummyReceiver([[$envelope]]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ResetServicesListener(new ServicesResetter(new \ArrayIterator([$resettableReceiver]), ['reset'])));
        $dispatcher->addListener(WorkerRunningEvent::class, function (WorkerRunningEvent $event) {
            $event->getWorker()->stop();
        });

        $bus = self::createMock(MessageBusInterface::class);
        $worker = new Worker([$resettableReceiver], $bus, $dispatcher);
        $worker->run();
        self::assertTrue($resettableReceiver->hasBeenReset());
    }

    public function testWorkerDoesNotResetTransportsIfResetServicesListenerIsNotCalled()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $resettableReceiver = new ResettableDummyReceiver([[$envelope]]);

        $bus = self::createMock(MessageBusInterface::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, function (WorkerRunningEvent $event) {
            $event->getWorker()->stop();
        });

        $worker = new Worker([$resettableReceiver], $bus, $dispatcher);
        $worker->run();
        self::assertFalse($resettableReceiver->hasBeenReset());
    }

    public function testWorkerDoesNotSendNullMessagesToTheBus()
    {
        $receiver = new DummyReceiver([
            null,
        ]);

        $bus = self::createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, function (WorkerRunningEvent $event) {
            $event->getWorker()->stop();
        });

        $worker = new Worker([$receiver], $bus, $dispatcher);
        $worker->run();
    }

    public function testWorkerDispatchesEventsOnSuccess()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $receiver = new DummyReceiver([[$envelope]]);

        $bus = self::createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturn($envelope);

        $eventDispatcher = self::createMock(EventDispatcherInterface::class);

        $eventDispatcher->expects(self::exactly(5))
            ->method('dispatch')
            ->withConsecutive(
                [self::isInstanceOf(WorkerStartedEvent::class)],
                [self::isInstanceOf(WorkerMessageReceivedEvent::class)],
                [self::isInstanceOf(WorkerMessageHandledEvent::class)],
                [self::isInstanceOf(WorkerRunningEvent::class)],
                [self::isInstanceOf(WorkerStoppedEvent::class)]
            )->willReturnCallback(function ($event) {
                if ($event instanceof WorkerRunningEvent) {
                    $event->getWorker()->stop();
                }

                return $event;
            });

        $worker = new Worker([$receiver], $bus, $eventDispatcher);
        $worker->run();
    }

    public function testWorkerDispatchesEventsOnError()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $receiver = new DummyReceiver([[$envelope]]);

        $bus = self::createMock(MessageBusInterface::class);
        $exception = new \InvalidArgumentException('Oh no!');
        $bus->method('dispatch')->willThrowException($exception);

        $eventDispatcher = self::createMock(EventDispatcherInterface::class);

        $eventDispatcher->expects(self::exactly(5))
            ->method('dispatch')
            ->withConsecutive(
                [self::isInstanceOf(WorkerStartedEvent::class)],
                [self::isInstanceOf(WorkerMessageReceivedEvent::class)],
                [self::isInstanceOf(WorkerMessageFailedEvent::class)],
                [self::isInstanceOf(WorkerRunningEvent::class)],
                [self::isInstanceOf(WorkerStoppedEvent::class)]
            )->willReturnCallback(function ($event) {
                if ($event instanceof WorkerRunningEvent) {
                    $event->getWorker()->stop();
                }

                return $event;
            });

        $worker = new Worker([$receiver], $bus, $eventDispatcher);
        $worker->run();
    }

    public function testWorkerContainsMetadata()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $receiver = new DummyQueueReceiver([[$envelope]]);

        $bus = self::createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturn($envelope);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkerRunningEvent::class, function (WorkerRunningEvent $event) {
            $event->getWorker()->stop();
        });

        $worker = new Worker(['dummyReceiver' => $receiver], $bus, $dispatcher);
        $worker->run(['queues' => ['queue1', 'queue2']]);

        $workerMetadata = $worker->getMetadata();

        self::assertSame(['queue1', 'queue2'], $workerMetadata->getQueueNames());
        self::assertSame(['dummyReceiver'], $workerMetadata->getTransportNames());
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

        $bus = self::createMock(MessageBusInterface::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(5));

        $worker = new Worker([$receiver], $bus, $dispatcher);
        $startTime = microtime(true);
        // sleep .1 after each idle
        $worker->run(['sleep' => 100000]);

        $duration = microtime(true) - $startTime;
        // wait time should be .3 seconds
        // use .29 & .31 for timing "wiggle room"
        self::assertGreaterThanOrEqual(.29, $duration);
        self::assertLessThan(.31, $duration);
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

        $bus = self::createMock(MessageBusInterface::class);

        $processedEnvelopes = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(6));
        $dispatcher->addListener(WorkerMessageReceivedEvent::class, function (WorkerMessageReceivedEvent $event) use (&$processedEnvelopes) {
            $processedEnvelopes[] = $event->getEnvelope();
        });
        $worker = new Worker([$receiver1, $receiver2, $receiver3], $bus, $dispatcher);
        $worker->run();

        // make sure they were processed in the correct order
        self::assertSame([$envelope1, $envelope2, $envelope3, $envelope4, $envelope5, $envelope6], $processedEnvelopes);
    }

    public function testWorkerLimitQueues()
    {
        $envelope = [new Envelope(new DummyMessage('message1'))];
        $receiver = self::createMock(QueueReceiverInterface::class);
        $receiver->expects(self::once())
            ->method('getFromQueues')
            ->with(['foo'])
            ->willReturn($envelope)
        ;
        $receiver->expects(self::never())
            ->method('get')
        ;

        $bus = self::getMockBuilder(MessageBusInterface::class)->getMock();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $worker = new Worker(['transport' => $receiver], $bus, $dispatcher);
        $worker->run(['queues' => ['foo']]);
    }

    public function testWorkerLimitQueuesUnsupported()
    {
        $receiver1 = self::createMock(QueueReceiverInterface::class);
        $receiver2 = self::createMock(ReceiverInterface::class);

        $bus = self::getMockBuilder(MessageBusInterface::class)->getMock();

        $worker = new Worker(['transport1' => $receiver1, 'transport2' => $receiver2], $bus);
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage(sprintf('Receiver for "transport2" does not implement "%s".', QueueReceiverInterface::class));
        $worker->run(['queues' => ['foo']]);
    }

    public function testWorkerMessageReceivedEventMutability()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));
        $receiver = new DummyReceiver([[$envelope]]);

        $bus = self::createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnArgument(0);

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $stamp = new class() implements StampInterface {
        };
        $listener = function (WorkerMessageReceivedEvent $event) use ($stamp) {
            $event->addStamps($stamp);
        };

        $eventDispatcher->addListener(WorkerMessageReceivedEvent::class, $listener);

        $worker = new Worker([$receiver], $bus, $eventDispatcher);
        $worker->run();

        $envelope = current($receiver->getAcknowledgedEnvelopes());
        self::assertCount(1, $envelope->all(\get_class($stamp)));
    }

    public function testWorkerShouldLogOnStop()
    {
        $bus = self::createMock(MessageBusInterface::class);
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with('Stopping worker.');
        $worker = new Worker([], $bus, new EventDispatcher(), $logger);

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
                self::assertSame(2, $receiver->getAcknowledgeCount());
            } else {
                self::assertSame(0, $receiver->getAcknowledgeCount());
            }
        });

        $worker = new Worker([$receiver], $bus, $dispatcher);
        $worker->run();

        self::assertSame($expectedMessages, $handler->processedMessages);
    }

    public function testFlushBatchOnIdle()
    {
        $expectedMessages = [
            new DummyMessage('Hey'),
        ];

        $receiver = new DummyReceiver([
            [new Envelope($expectedMessages[0])],
            [],
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
                self::assertSame(1, $receiver->getAcknowledgeCount());
            } else {
                self::assertSame(0, $receiver->getAcknowledgeCount());
            }
        });

        $worker = new Worker([$receiver], $bus, $dispatcher);
        $worker->run();

        self::assertSame($expectedMessages, $handler->processedMessages);
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
            self::assertSame(0, $receiver->getAcknowledgeCount());
        });

        $worker = new Worker([$receiver], $bus, $dispatcher);
        $worker->run();

        self::assertSame($expectedMessages, $handler->processedMessages);
    }
}

class DummyReceiver implements ReceiverInterface
{
    private $deliveriesOfEnvelopes;
    private $acknowledgedEnvelopes;
    private $rejectedEnvelopes;
    private $acknowledgeCount = 0;
    private $rejectCount = 0;

    /**
     * @param Envelope[][] $deliveriesOfEnvelopes
     */
    public function __construct(array $deliveriesOfEnvelopes)
    {
        $this->deliveriesOfEnvelopes = $deliveriesOfEnvelopes;
    }

    public function get(): iterable
    {
        $val = array_shift($this->deliveriesOfEnvelopes);

        return $val ?? [];
    }

    public function ack(Envelope $envelope): void
    {
        ++$this->acknowledgeCount;
        $this->acknowledgedEnvelopes[] = $envelope;
    }

    public function reject(Envelope $envelope): void
    {
        ++$this->rejectCount;
        $this->rejectedEnvelopes[] = $envelope;
    }

    public function getAcknowledgeCount(): int
    {
        return $this->acknowledgeCount;
    }

    public function getRejectCount(): int
    {
        return $this->rejectCount;
    }

    public function getAcknowledgedEnvelopes(): array
    {
        return $this->acknowledgedEnvelopes;
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

    public $processedMessages;

    public function __invoke(DummyMessage $message, Acknowledger $ack = null)
    {
        return $this->handle($message, $ack);
    }

    private function shouldFlush()
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

class ResettableDummyReceiver extends DummyReceiver implements ResetInterface
{
    private $hasBeenReset = false;

    public function reset()
    {
        $this->hasBeenReset = true;
    }

    public function hasBeenReset(): bool
    {
        return $this->hasBeenReset;
    }
}
