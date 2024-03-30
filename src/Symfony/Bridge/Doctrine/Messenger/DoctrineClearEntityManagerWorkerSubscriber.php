<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Messenger;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * Clears entity managers between messages being handled to avoid outdated data.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class DoctrineClearEntityManagerWorkerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    public function onWorkerMessageHandled(): void
    {
        $this->clearEntityManagers();
    }

    public function onWorkerMessageFailed(): void
    {
        $this->clearEntityManagers();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageHandledEvent::class => 'onWorkerMessageHandled',
            WorkerMessageFailedEvent::class => 'onWorkerMessageFailed',
        ];
    }

    private function clearEntityManagers(): void
    {
        foreach ($this->managerRegistry->getManagers() as $manager) {
            $manager->clear();
        }
    }
}
