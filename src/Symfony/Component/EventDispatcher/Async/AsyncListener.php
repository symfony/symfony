<?php

namespace Symfony\Component\EventDispatcher\Async;

use Interop\Queue\PsrContext;
use Interop\Queue\PsrQueue;
use Symfony\Component\EventDispatcher\Event;

class AsyncListener
{
    /**
     * @var PsrContext
     */
    private $context;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var PsrQueue
     */
    private $eventQueue;

    /**
     * @var bool
     */
    private $syncMode;

    /**
     * @param PsrContext      $context
     * @param Registry        $registry
     * @param PsrQueue|string $eventQueue
     */
    public function __construct(PsrContext $context, Registry $registry, $eventQueue)
    {
        $this->context = $context;
        $this->registry = $registry;
        $this->eventQueue = $eventQueue instanceof PsrQueue ? $eventQueue : $context->createQueue($eventQueue);
    }

    public function __invoke(Event $event, $eventName)
    {
        $this->onEvent($event, $eventName);
    }

    public function resetSyncMode()
    {
        $this->syncMode = [];
    }

    /**
     * @param string $eventName
     */
    public function syncMode($eventName)
    {
        $this->syncMode[$eventName] = true;
    }

    /**
     * @param string $eventName
     *
     * @return bool
     */
    public function isSyncMode($eventName)
    {
        return isset($this->syncMode[$eventName]);
    }

    /**
     * @param Event  $event
     * @param string $eventName
     */
    public function onEvent(Event $event, $eventName)
    {
        if (false == isset($this->syncMode[$eventName])) {
            $transformerName = $this->registry->getTransformerNameForEvent($eventName);

            $message = $this->registry->getTransformer($transformerName)->toMessage($eventName, $event);
            $message->setProperty('event_name', $eventName);
            $message->setProperty('transformer_name', $transformerName);

            $this->context->createProducer()->send($this->eventQueue, $message);
        }
    }
}
