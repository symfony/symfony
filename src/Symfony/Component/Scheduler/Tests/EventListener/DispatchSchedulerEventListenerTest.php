<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Scheduler\Event\FailureEvent;
use Symfony\Component\Scheduler\Event\PostRunEvent;
use Symfony\Component\Scheduler\Event\PreRunEvent;
use Symfony\Component\Scheduler\EventListener\DispatchSchedulerEventListener;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Messenger\ScheduledStamp;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Scheduler\Tests\Fixtures\SomeScheduleProvider;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class DispatchSchedulerEventListenerTest extends TestCase
{
    public function testDispatchSchedulerEvents()
    {
        $trigger = $this->createStub(TriggerInterface::class);
        $defaultRecurringMessage = RecurringMessage::trigger($trigger, (object) ['id' => 'default']);

        $schedulerProvider = new SomeScheduleProvider([$defaultRecurringMessage]);
        $scheduleProviderLocator = new Container();
        $scheduleProviderLocator->set('default', $schedulerProvider);

        $context = new MessageContext('default', 'default', $trigger, new \DateTimeImmutable());
        $envelope = (new Envelope(new \stdClass()))->with(new ScheduledStamp($context));

        $listener = new DispatchSchedulerEventListener($scheduleProviderLocator, $eventDispatcher = new EventDispatcher());
        $workerReceivedEvent = new WorkerMessageReceivedEvent($envelope, 'default');
        $workerHandledEvent = new WorkerMessageHandledEvent($envelope->with(new HandledStamp('result', 'handlerName')), 'default');
        $workerFailedEvent = new WorkerMessageFailedEvent($envelope, 'default', new \Exception('failed'));
        $secondListener = new TestEventListener();

        $eventDispatcher->addListener(PreRunEvent::class, [$secondListener, 'preRun']);
        $eventDispatcher->addListener(PostRunEvent::class, [$secondListener, 'postRun']);
        $eventDispatcher->addListener(FailureEvent::class, [$secondListener, 'onFailure']);
        $listener->onMessageReceived($workerReceivedEvent);
        $listener->onMessageHandled($workerHandledEvent);
        $listener->onMessageFailed($workerFailedEvent);

        $this->assertInstanceOf(PreRunEvent::class, $secondListener->preRunEvent);
        $this->assertInstanceOf(PostRunEvent::class, $secondListener->postRunEvent);
        $this->assertSame('result', $secondListener->postRunEvent->getResult());
        $this->assertInstanceOf(FailureEvent::class, $secondListener->failureEvent);
        $this->assertEquals(new \Exception('failed'), $secondListener->failureEvent->getError());
    }

    #[DataProvider('redispatchedMessageProvider')]
    public function testRedispatchedMessagesAreUnwrappedForListeners(\Closure $wrap)
    {
        $trigger = $this->createStub(TriggerInterface::class);
        $message = (object) ['id' => 'default'];

        $scheduleProviderLocator = new Container();
        $scheduleProviderLocator->set('default', new SomeScheduleProvider([RecurringMessage::trigger($trigger, $message)]));

        $stamp = new ScheduledStamp(new MessageContext('default', 'default', $trigger, new \DateTimeImmutable()));
        $envelope = new Envelope($wrap($message, $stamp), [$stamp]);

        $listener = new DispatchSchedulerEventListener($scheduleProviderLocator, $eventDispatcher = new EventDispatcher());
        $secondListener = new TestEventListener();

        $eventDispatcher->addListener(PreRunEvent::class, [$secondListener, 'preRun']);
        $eventDispatcher->addListener(PostRunEvent::class, [$secondListener, 'postRun']);
        $eventDispatcher->addListener(FailureEvent::class, [$secondListener, 'onFailure']);
        $listener->onMessageReceived(new WorkerMessageReceivedEvent($envelope, 'default'));
        $listener->onMessageHandled(new WorkerMessageHandledEvent($envelope->with(new HandledStamp('result', 'handlerName')), 'default'));
        $listener->onMessageFailed(new WorkerMessageFailedEvent($envelope, 'default', new \Exception('failed')));

        $this->assertSame($message, $secondListener->preRunEvent->getMessage());
        $this->assertSame($message, $secondListener->postRunEvent->getMessage());
        $this->assertSame($message, $secondListener->failureEvent->getMessage());
    }

    public static function redispatchedMessageProvider(): iterable
    {
        yield 'wrapped envelope' => [static fn (object $message, ScheduledStamp $stamp) => new RedispatchMessage(new Envelope($message, [$stamp]))];
        yield 'wrapped message' => [static fn (object $message) => new RedispatchMessage($message, 'async')];
    }

    public function testTheListenersOfAScheduleRunForItsOwnMessagesOnly()
    {
        $trigger = $this->createStub(TriggerInterface::class);
        $called = [];

        $createProvider = static function (string $name) use ($trigger, &$called) {
            $schedule = (new Schedule())
                ->add(RecurringMessage::trigger($trigger, (object) ['id' => $name]))
                ->before(static function () use ($name, &$called) { $called[] = $name; });

            return new class($schedule) implements ScheduleProviderInterface {
                public function __construct(private readonly Schedule $schedule)
                {
                }

                public function getSchedule(): Schedule
                {
                    return $this->schedule;
                }
            };
        };

        $scheduleProviderLocator = new Container();
        $scheduleProviderLocator->set('first', $createProvider('first'));
        $scheduleProviderLocator->set('second', $createProvider('second'));

        $listener = new DispatchSchedulerEventListener($scheduleProviderLocator, new EventDispatcher());

        $context = new MessageContext('first', 'first', $trigger, new \DateTimeImmutable());
        $envelope = (new Envelope(new \stdClass()))->with(new ScheduledStamp($context));

        $listener->onMessageReceived(new WorkerMessageReceivedEvent($envelope, 'default'));

        $this->assertSame(['first'], $called);
    }

    public function testCanceledMessageIsRejected()
    {
        $trigger = $this->createStub(TriggerInterface::class);
        $defaultRecurringMessage = RecurringMessage::trigger($trigger, (object) ['id' => 'default']);

        $schedulerProvider = new SomeScheduleProvider([$defaultRecurringMessage]);
        $scheduleProviderLocator = $this->createStub(ContainerInterface::class);
        $scheduleProviderLocator->method('has')->willReturn(true);
        $scheduleProviderLocator->method('get')->willReturn($schedulerProvider);

        $context = new MessageContext('default', 'default', $trigger, new \DateTimeImmutable());
        $envelope = (new Envelope(new \stdClass()))->with(new ScheduledStamp($context));

        $receiver = $this->createMock(ReceiverInterface::class);
        $receiver->expects($this->once())->method('reject')->with($envelope);
        $receiverLocator = $this->createStub(ContainerInterface::class);
        $receiverLocator->method('has')->willReturn(true);
        $receiverLocator->method('get')->willReturn($receiver);

        $listener = new DispatchSchedulerEventListener($scheduleProviderLocator, $eventDispatcher = new EventDispatcher(), $receiverLocator);
        $eventDispatcher->addListener(PreRunEvent::class, static function (PreRunEvent $event) { $event->shouldCancel(true); });

        $workerReceivedEvent = new WorkerMessageReceivedEvent($envelope, 'async');
        $listener->onMessageReceived($workerReceivedEvent);

        $this->assertFalse($workerReceivedEvent->shouldHandle());
    }

    public function testNotCanceledMessageIsNotRejected()
    {
        $trigger = $this->createStub(TriggerInterface::class);
        $defaultRecurringMessage = RecurringMessage::trigger($trigger, (object) ['id' => 'default']);

        $schedulerProvider = new SomeScheduleProvider([$defaultRecurringMessage]);
        $scheduleProviderLocator = $this->createStub(ContainerInterface::class);
        $scheduleProviderLocator->method('has')->willReturn(true);
        $scheduleProviderLocator->method('get')->willReturn($schedulerProvider);

        $context = new MessageContext('default', 'default', $trigger, new \DateTimeImmutable());
        $envelope = (new Envelope(new \stdClass()))->with(new ScheduledStamp($context));

        $receiverLocator = $this->createMock(ContainerInterface::class);
        $receiverLocator->expects($this->never())->method('has');
        $receiverLocator->expects($this->never())->method('get');

        $listener = new DispatchSchedulerEventListener($scheduleProviderLocator, new EventDispatcher(), $receiverLocator);

        $workerReceivedEvent = new WorkerMessageReceivedEvent($envelope, 'async');
        $listener->onMessageReceived($workerReceivedEvent);

        $this->assertTrue($workerReceivedEvent->shouldHandle());
    }
}

class TestEventListener
{
    public ?PreRunEvent $preRunEvent = null;
    public ?PostRunEvent $postRunEvent = null;
    public ?FailureEvent $failureEvent = null;

    /* Listener methods */

    public function preRun($e)
    {
        $this->preRunEvent = $e;
    }

    public function postRun($e)
    {
        $this->postRunEvent = $e;
    }

    public function onFailure($e)
    {
        $this->failureEvent = $e;
    }
}
