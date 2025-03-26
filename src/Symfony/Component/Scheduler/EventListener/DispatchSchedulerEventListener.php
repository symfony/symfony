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
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Scheduler\Event\FailureEvent;
use Symfony\Component\Scheduler\Event\PostRunEvent;
use Symfony\Component\Scheduler\Event\PreRunEvent;
use Symfony\Component\Scheduler\Messenger\ScheduledStamp;

class DispatchSchedulerEventListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ContainerInterface $scheduleProviderLocator,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $envelope = $event->getEnvelope();

        if (!$scheduledStamp = $this->getScheduledStamp($envelope)) {
            return;
        }

        $result = $envelope->last(HandledStamp::class)?->getResult();

        $this->eventDispatcher->dispatch(new PostRunEvent($this->scheduleProviderLocator->get($scheduledStamp->messageContext->name), $scheduledStamp->messageContext, $envelope->getMessage(), $result));
    }

    public function onMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $envelope = $event->getEnvelope();

        if (!$scheduledStamp = $this->getScheduledStamp($envelope)) {
            return;
        }

        $preRunEvent = new PreRunEvent($this->scheduleProviderLocator->get($scheduledStamp->messageContext->name), $scheduledStamp->messageContext, $envelope->getMessage());

        $this->eventDispatcher->dispatch($preRunEvent);

        if ($preRunEvent->shouldCancel()) {
            $event->shouldHandle(false);
        }
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $envelope = $event->getEnvelope();

        if (!$scheduledStamp = $this->getScheduledStamp($envelope)) {
            return;
        }

        $this->eventDispatcher->dispatch(new FailureEvent($this->scheduleProviderLocator->get($scheduledStamp->messageContext->name), $scheduledStamp->messageContext, $envelope->getMessage(), $event->getThrowable()));
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
