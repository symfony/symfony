<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MultipleHandlersForMessageException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

/**
 * Leverages a message bus to expect a single, synchronous message handling
 * and return its result; or return null if asynchronous handling.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
trait HandleTrait
{
    private MessageBusInterface $messageBus;
    private bool $allowAsyncHandling = false;

    /**
     * Dispatches the given message, expecting to be handled by a single handler
     * and returns the result from the handler returned value. If the message was
     * handled asynchronously, this method returns null.
     * This behavior is useful for both command & query buses,
     * the last one usually returning the handler result.
     *
     * @param object|Envelope $message The message or the message pre-wrapped in an envelope
     */
    private function handle(object $message): mixed
    {
        if (!isset($this->messageBus)) {
            throw new LogicException(sprintf('You must provide a "%s" instance in the "%s::$messageBus" property, but that property has not been initialized yet.', MessageBusInterface::class, static::class));
        }

        $envelope = $this->messageBus->dispatch($message);
        /** @var HandledStamp[] $handledStamps */
        $handledStamps = $envelope->all(HandledStamp::class);

        if (!$handledStamps) {
            if ($this->allowAsyncHandling && $envelope->all(SentStamp::class)) {
                // it went through an asynchronous transport
                return null;
            }

            throw new NoHandlerForMessageException(sprintf('Message of type "%s" was handled zero times. Exactly one handler is expected when using "%s::%s()".', get_debug_type($envelope->getMessage()), static::class, __FUNCTION__));
        }

        if (\count($handledStamps) > 1) {
            $handlers = implode(', ', array_map(fn (HandledStamp $stamp): string => sprintf('"%s"', $stamp->getHandlerName()), $handledStamps));

            throw new MultipleHandlersForMessageException(sprintf('Message of type "%s" was handled multiple times. Only one handler is expected when using "%s::%s()", got %d: %s.', get_debug_type($envelope->getMessage()), static::class, __FUNCTION__, \count($handledStamps), $handlers));
        }

        return $handledStamps[0]->getResult();
    }
}
