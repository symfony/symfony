<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Messenger\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bridge\Doctrine\Messenger\EntityMessage\EntityMessageCollectionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Doctrine listener that listens to Persist, Update and Remove. Every time this is
 * invoked we take messages from the entities.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 */
class EntityMessageCollector implements EventSubscriber
{
    private $messageBus;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postFlush,
        ];
    }

    public function postFlush(LifecycleEventArgs $event)
    {
        $this->collectEventsFromEntity($event);
    }

    private function collectEventsFromEntity(LifecycleEventArgs $message)
    {
        $entity = $message->getEntity();

        if ($entity instanceof EntityMessageCollectionInterface) {
            foreach ($entity->getRecordedMessages() as $message) {
                $this->messageBus->dispatch($message, [new DispatchAfterCurrentBusStamp()]);
            }

            $entity->resetRecordedMessages();
        }
    }
}
