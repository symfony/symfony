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

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 * @author Valentin Udaltsov <udaltsov.valentin@gmail.com>
 */
final class DispatchEntityMessagesDoctrineSubscriber implements EventSubscriber
{
    private $bus;
    private $dispatcher;

    public function __construct(MessageBusInterface $bus, EventDispatcherInterface $dispatcher)
    {
        $this->bus = $bus;
        $this->dispatcher = $dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::postFlush,
        ];
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        foreach ($args->getEntityManager()->getUnitOfWork()->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                if (!$entity instanceof MessageRecordingEntityInterface) {
                    continue;
                }

                $entity->dispatchMessages(function (array $messages) use ($entity): void {
                    foreach ($messages as $message) {
                        $envelope = Envelope::wrap($message, [new DispatchAfterCurrentBusStamp()]);
                        $event = new EntityMessagePreDispatchEvent($entity, $envelope);
                        $this->dispatcher->dispatch($event);
                        $this->bus->dispatch($event->getEnvelope());
                    }
                });
            }
        }
    }
}
