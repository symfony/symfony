<?php

namespace Symfony\Component\EventDispatcher\Async;

use Interop\Queue\PsrContext;
use Interop\Queue\PsrMessage;
use Symfony\Component\EventDispatcher\Event;

class PhpSerializerEventTransformer implements EventTransformer
{
    /**
     * @var PsrContext
     */
    private $context;

    /**
     * @param PsrContext $context
     */
    public function __construct(PsrContext $context)
    {
        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    public function toMessage($eventName, Event $event = null)
    {
        return $this->context->createMessage(serialize($event));
    }

    /**
     * {@inheritdoc}
     */
    public function toEvent($eventName, PsrMessage $message)
    {
        return unserialize($message->getBody());
    }
}
