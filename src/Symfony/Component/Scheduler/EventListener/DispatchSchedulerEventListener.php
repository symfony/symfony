<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\EventListener;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Scheduler\Event\FailureEvent;
use Symfony\Component\Scheduler\Event\PostRunEvent;
use Symfony\Component\Scheduler\Event\PreRunEvent;
use Symfony\Component\Scheduler\Messenger\ScheduledStamp;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

class DispatchSchedulerEventListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ContainerInterface $scheduleProviderLocator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?ContainerInterface $receiverLocator = null,
    ) {
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $envelope = $event->getEnvelope();

        if (!$scheduledStamp = $this->getScheduledStamp($envelope)) {
            return;
        }

        $result = $envelope->last(HandledStamp::class)?->getResult();
        $provider = $this->scheduleProviderLocator->get($scheduledStamp->messageContext->name);

        $this->dispatch($provider, new PostRunEvent($provider, $scheduledStamp->messageContext, $this->getMessage($envelope), $result));
    }

    public function onMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $envelope = $event->getEnvelope();

        if (!$scheduledStamp = $this->getScheduledStamp($envelope)) {
            return;
        }

        $provider = $this->scheduleProviderLocator->get($scheduledStamp->messageContext->name);
        $preRunEvent = new PreRunEvent($provider, $scheduledStamp->messageContext, $this->getMessage($envelope));

        $this->dispatch($provider, $preRunEvent);

        if ($preRunEvent->shouldCancel()) {
            $event->shouldHandle(false);

            $receiverName = $event->getReceiverName();

            if ($this->receiverLocator?->has($receiverName)) {
                $this->receiverLocator->get($receiverName)->reject($envelope);
            }
        }
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $envelope = $event->getEnvelope();

        if (!$scheduledStamp = $this->getScheduledStamp($envelope)) {
            return;
        }

        $provider = $this->scheduleProviderLocator->get($scheduledStamp->messageContext->name);

        $this->dispatch($provider, new FailureEvent($provider, $scheduledStamp->messageContext, $this->getMessage($envelope), $event->getThrowable()));
    }

    /**
     * Notifies the listeners of the application, then the ones registered on the schedule itself.
     */
    private function dispatch(ScheduleProviderInterface $provider, object $event): void
    {
        $this->eventDispatcher->dispatch($event);
        $provider->getSchedule()->getEventDispatcher()?->dispatch($event);
    }

    /**
     * Unwraps messages that scheduler transports redispatch, so that listeners always get the scheduled message.
     */
    private function getMessage(Envelope $envelope): object
    {
        $message = $envelope->getMessage();

        if (!$message instanceof RedispatchMessage) {
            return $message;
        }

        return $message->envelope instanceof Envelope ? $message->envelope->getMessage() : $message->envelope;
    }

    private function getScheduledStamp(Envelope $envelope): ?StampInterface
    {
        if (!$scheduledStamp = $envelope->last(ScheduledStamp::class)) {
            return null;
        }

        if (!$this->scheduleProviderLocator->has($scheduledStamp->messageContext->name)) {
            return null;
        }

        return $scheduledStamp;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => ['onMessageReceived'],
            WorkerMessageHandledEvent::class => ['onMessageHandled'],
            WorkerMessageFailedEvent::class => ['onMessageFailed'],
        ];
    }
}
