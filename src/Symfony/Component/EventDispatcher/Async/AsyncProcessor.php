<?php

namespace Symfony\Component\EventDispatcher\Async;

use Interop\Queue\PsrContext;
use Interop\Queue\PsrMessage;
use Interop\Queue\PsrProcessor;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AsyncProcessor implements PsrProcessor
{
    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var AsyncEventDispatcher
     */
    private $dispatcher;

    /**
     * @param Registry             $registry
     * @param AsyncEventDispatcher $dispatcher
     */
    public function __construct(Registry $registry, AsyncEventDispatcher $dispatcher)
    {
        $this->registry = $registry;
        $this->dispatcher = $dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function process(PsrMessage $message, PsrContext $context)
    {
        if (false == $eventName = $message->getProperty('event_name')) {
            return self::REJECT;
        }
        if (false == $transformerName = $message->getProperty('transformer_name')) {
            return self::REJECT;
        }

        $event = $this->registry->getTransformer($transformerName)->toEvent($eventName, $message);

        $this->dispatcher->dispatchAsyncListenersOnly($eventName, $event);

        return self::ACK;
    }
}
