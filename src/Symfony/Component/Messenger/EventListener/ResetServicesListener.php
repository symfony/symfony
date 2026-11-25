<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ServicesResetterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessagesCompletedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\Worker;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class ResetServicesListener implements EventSubscriberInterface
{
    private int $interval = 1;
    private int $count = 0;
    private bool $resetPending = false;

    public function __construct(
        #[Autowire(service: ServicesResetterInterface::class)]
        private ResetInterface $servicesResetter,
    ) {
    }

    public function setInterval(int $interval): void
    {
        $this->interval = $interval;
    }

    public function resetServices(WorkerRunningEvent $event): void
    {
        if (!$event->isWorkerIdle() && 0 === ++$this->count % $this->interval) {
            $this->resetPending = true;
            // no new message is started: under a steady load the ones in flight would never be all handled
            $event->getWorker()->pauseConsumption();
        }

        $this->resetIfNoMessageIsInFlight($event->getWorker());
    }

    public function resetServicesWhenMessagesCompleted(WorkerMessagesCompletedEvent $event): void
    {
        $this->resetIfNoMessageIsInFlight($event->getWorker());
    }

    public function resetServicesAtStop(WorkerStoppedEvent $event): void
    {
        $this->servicesResetter->reset();
    }

    private function resetIfNoMessageIsInFlight(Worker $worker): void
    {
        // the services are shared by the messages in flight: wait until the last one is handled
        if ($this->resetPending && 0 === $worker->getInFlightMessageCount()) {
            $this->resetPending = false;
            $this->servicesResetter->reset();
            $worker->resumeConsumption();
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerRunningEvent::class => ['resetServices', -1024],
            WorkerMessagesCompletedEvent::class => ['resetServicesWhenMessagesCompleted', -1024],
            WorkerStoppedEvent::class => ['resetServicesAtStop', -1024],
        ];
    }
}
