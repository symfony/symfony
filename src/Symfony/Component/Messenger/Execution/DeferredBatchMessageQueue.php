<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Execution;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Execution\Message\DeferredBatchMessage;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;

/**
 * @internal
 */
final class DeferredBatchMessageQueue
{
    /**
     * The messages that wait for their batch handler, in the order the strategy reported them.
     *
     * A strategy running messages concurrently may report them out of order, e.g. the message that flushed
     * the batch after the ones that came next: the batch handler is then flushed with a message still pending.
     *
     * @var \SplObjectStorage<object, non-empty-list<DeferredBatchMessage>>|null
     */
    private ?\SplObjectStorage $messages = null;

    public function hasPending(): bool
    {
        return null !== $this->messages;
    }

    public function add(object $batchHandler, mixed $context, Envelope $envelope, bool &$acked, float $queuedAt): void
    {
        /** @var \SplObjectStorage<object, non-empty-list<DeferredBatchMessage>> $messages */
        $messages = $this->messages ??= new \SplObjectStorage();

        // forget the messages that got their outcome meanwhile
        $pending = isset($messages[$batchHandler]) ? array_values(array_filter($messages[$batchHandler], static fn (DeferredBatchMessage $message) => !$message->acked)) : [];
        $pending[] = new DeferredBatchMessage($context, $envelope, $acked, $queuedAt);
        $messages[$batchHandler] = $pending;
    }

    /**
     * @return \SplObjectStorage<object, DeferredBatchMessage>
     */
    public function popFlushable(bool|float $force, float $now): \SplObjectStorage
    {
        if (!$this->messages) {
            return new \SplObjectStorage();
        }

        /** @var \SplObjectStorage<object, DeferredBatchMessage> $flushable */
        $flushable = new \SplObjectStorage();

        if (\is_bool($force)) {
            foreach ($this->messages as $handler) {
                $flushable[$handler] = self::messageToFlushWith($this->messages[$handler]);
            }

            $this->messages = null;

            return $flushable;
        }

        /** @var \SplObjectStorage<object, non-empty-list<DeferredBatchMessage>> $remaining */
        $remaining = new \SplObjectStorage();

        foreach ($this->messages as $handler) {
            $messages = $this->messages[$handler];

            if ($force <= $now - end($messages)->queuedAt) {
                $flushable[$handler] = self::messageToFlushWith($messages);
            } else {
                $remaining[$handler] = $messages;
            }
        }

        $this->messages = $remaining->count() ? $remaining : null;

        return $flushable;
    }

    /**
     * Returns the message that the flush of a batch handler is dispatched with: the last one still pending,
     * since the flush is skipped for a message that has its outcome already.
     *
     * @param non-empty-list<DeferredBatchMessage> $messages
     */
    private static function messageToFlushWith(array $messages): DeferredBatchMessage
    {
        foreach (array_reverse($messages) as $message) {
            if (!$message->acked) {
                return $message;
            }
        }

        return end($messages);
    }

    /**
     * Combines the acks that the batch handlers of a message sent, once they all did.
     *
     * Returns null while some batch handlers didn't send theirs, or when the message already has its outcome.
     *
     * @param array{Envelope, list<array>}|null $batch The dispatched envelope and the acks received so far, null once the message has its outcome
     */
    public static function combineAcks(?array &$batch, array $acks): ?array
    {
        if (null === $batch) {
            return null;
        }

        [$envelope, $received] = $batch;
        foreach ($acks as [$transportName, $ackEnvelope, $e]) {
            $received[] = [$transportName, $ackEnvelope, $e];
        }

        $pending = [];
        $handledStamps = [];
        foreach ($envelope->all(HandledStamp::class) as $stamp) {
            $handledStamps[$stamp->getHandlerName()] = $stamp;
        }
        foreach ($envelope->all(NoAutoAckStamp::class) as $stamp) {
            $name = $stamp->getHandlerDescriptor()->getName();
            $pending[$name] = true;
            unset($handledStamps[$name]);
        }

        $exceptions = [];
        foreach ($received as [, $ackEnvelope, $e]) {
            if ($e instanceof HandlerFailedException) {
                foreach ($e->getWrappedExceptions() as $name => $exception) {
                    unset($pending[$name]);
                    $exceptions[$name] = $exception;
                }
            } elseif (null !== $e) {
                // an error that isn't tied to a batch handler gives the message its outcome
                $pending = [];
                $exceptions[] = $e;
            } elseif ($stamp = $ackEnvelope->last(HandledStamp::class)) {
                $name = $stamp->getHandlerName();
                unset($pending[$name]);
                $handledStamps[$name] = $stamp;
            }
        }

        if ($pending) {
            $batch = [$envelope, $received];

            return null;
        }

        $batch = null;

        if (1 === \count($received)) {
            return $received[0];
        }

        $envelope = $envelope->withoutAll(NoAutoAckStamp::class)->withoutAll(HandledStamp::class)->with(...array_values($handledStamps));

        return [$received[0][0], $envelope, $exceptions ? new HandlerFailedException($envelope, $exceptions) : null];
    }
}
