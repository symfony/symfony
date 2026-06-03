<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Allow to configure messages to be handled after the current bus is finished.
 *
 * I.e, messages dispatched from a handler with a DispatchAfterCurrentBus stamp
 * will actually be handled once the current message being dispatched is fully
 * handled.
 *
 * For instance, using this middleware before the DoctrineTransactionMiddleware
 * means sub-dispatched messages with a DispatchAfterCurrentBus stamp would be
 * handled after the Doctrine transaction has been committed.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class DispatchAfterCurrentBusMiddleware implements MiddlewareInterface
{
    /**
     * @var QueuedEnvelope[] A queue of messages and next middleware
     */
    private array $queue = [];

    /**
     * @var bool this property is used to signal if we are inside a the first/root call to
     *           MessageBusInterface::dispatch() or if dispatch has been called inside a message handler
     */
    private bool $isRootDispatchCallRunning = false;

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null !== $envelope->last(DispatchAfterCurrentBusStamp::class)) {
            if ($this->isRootDispatchCallRunning) {
                $this->queue[] = new QueuedEnvelope($envelope, $stack);

                return $envelope;
            }

            $envelope = $envelope->withoutAll(DispatchAfterCurrentBusStamp::class);
        }

        if ($this->isRootDispatchCallRunning) {
            /*
             * A call to MessageBusInterface::dispatch() was made from inside the main bus handling,
             * but the message does not have the stamp. So, process it like normal.
             */
            return $stack->next()->handle($envelope, $stack);
        }

        // First time we get here, mark as inside a "root dispatch" call:
        $this->isRootDispatchCallRunning = true;
        try {
            // Execute the whole middleware stack & message handling for main dispatch:
            $returnedEnvelope = $stack->next()->handle($envelope, $stack);
        } catch (\Throwable $exception) {
            /*
             * Whenever an exception occurs while handling a message that has
             * queued other messages, we drop the queued ones.
             * This is intentional since the queued commands were likely dependent
             * on the preceding command.
             */
            $this->queue = [];
            $this->isRootDispatchCallRunning = false;

            throw $exception;
        }

        // "Root dispatch" call is finished, dispatch stored messages.
        $exceptions = [];
        while (null !== $queueItem = array_shift($this->queue)) {
            // Save how many messages are left in queue before handling the message
            $queueLengthBefore = \count($this->queue);
            try {
                // Execute the stored messages
                $queueItem->getStack()->next()->handle($queueItem->getEnvelope(), $queueItem->getStack());
            } catch (\Exception $exception) {
                // Gather all exceptions
                $exceptions[] = $exception;
                // Restore queue to previous state
                $this->queue = \array_slice($this->queue, 0, $queueLengthBefore);
            }
        }

        $this->isRootDispatchCallRunning = false;
        if (\count($exceptions) > 0) {
            throw new DelayedMessageHandlingException($exceptions, $returnedEnvelope);
        }

        return $returnedEnvelope;
    }
}

/**
 * @internal
 */
final class QueuedEnvelope
{
    private Envelope $envelope;

    public function __construct(
        Envelope $envelope,
        private StackInterface $stack,
    ) {
        $this->envelope = $envelope->withoutAll(DispatchAfterCurrentBusStamp::class);
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    public function getStack(): StackInterface
    {
        return $this->stack;
    }
}
