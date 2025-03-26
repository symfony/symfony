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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Scheduler\Event\FailureEvent;
use Symfony\Component\Scheduler\Event\PostRunEvent;
use Symfony\Component\Scheduler\Event\PreRunEvent;
use Symfony\Component\Scheduler\EventListener\DispatchSchedulerEventListener;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Messenger\ScheduledStamp;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Tests\Fixtures\SomeScheduleProvider;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class DispatchSchedulerEventListenerTest extends TestCase
{
    public function testDispatchSchedulerEvents()
    {
        $trigger = $this->createMock(TriggerInterface::class);
        $defaultRecurringMessage = RecurringMessage::trigger($trigger, (object) ['id' => 'default']);

        $schedulerProvider = new SomeScheduleProvider([$defaultRecurringMessage]);
        $scheduleProviderLocator = new Container();
        $scheduleProviderLocator->set('default', $schedulerProvider);

        $context = new MessageContext('default', 'default', $trigger, $this->createMock(\DateTimeImmutable::class));
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
