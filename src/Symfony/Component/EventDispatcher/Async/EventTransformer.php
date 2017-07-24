<?php

namespace Symfony\Component\EventDispatcher\Async;

use Interop\Queue\PsrMessage;
use Symfony\Component\EventDispatcher\Event;

interface EventTransformer
{
    /**
     * @param string     $eventName
     * @param Event|null $event
     *
     * @return PsrMessage
     */
    public function toMessage($eventName, Event $event);

    /**
     * If you able to transform message back to event return it.
     * If you failed to transform for some reason you can return a string status (@see PsrProcess constants) or an object that implements __toString method.
     * The object must have a __toString method is supposed to be used as PsrProcessor::process return value.
     *
     * @param string     $eventName
     * @param PsrMessage $message
     *
     * @return Event|string|object
     */
    public function toEvent($eventName, PsrMessage $message);
}
