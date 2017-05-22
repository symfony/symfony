<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Worker\Consumer;

use Symfony\Component\Worker\MessageCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class EventConsumer implements ConsumerInterface
{
    private $consumer;
    private $eventDispatcher;
    private $name;

    public function __construct(ConsumerInterface $consumer, EventDispatcherInterface $eventDispatcher, $name = null)
    {
        $this->consumer = $consumer;
        $this->eventDispatcher = $eventDispatcher;
        $this->name = $name;
    }

    public function consume(MessageCollection $messageCollection)
    {
        $this->dispatch(ConsumerEvents::PRE_CONSUME, $messageCollection);

        $this->consumer->consume($messageCollection);

        $this->dispatch(ConsumerEvents::POST_CONSUME, $messageCollection);
    }

    private function dispatch($eventName, MessageCollection $messageCollection)
    {
        $event = new MessageCollectionEvent($messageCollection);

        $this->eventDispatcher->dispatch($eventName, $event);

        if ($this->name) {
            $localEventName = sprintf('%s.%s', $eventName, $this->name);
            $this->eventDispatcher->dispatch($localEventName, $event);
        }
    }
}
