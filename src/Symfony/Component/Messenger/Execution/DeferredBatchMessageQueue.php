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
    /** @var \SplObjectStorage<object, DeferredBatchMessage>|null */
    private ?\SplObjectStorage $messages = null;

    public function hasPending(): bool
    {
        return null !== $this->messages;
    }

    public function add(object $batchHandler, string $transportName, Envelope $envelope, bool &$acked, float $queuedAt): void
    {
        $this->messages ??= new \SplObjectStorage();
        $this->messages[$batchHandler] = new DeferredBatchMessage($transportName, $envelope, $acked, $queuedAt);
    }

    /**
     * @return \SplObjectStorage<object, DeferredBatchMessage>
     */
    public function popFlushable(bool|float $force, float $now): \SplObjectStorage
    {
        if (!$this->messages) {
            return new \SplObjectStorage();
        }

        if (\is_bool($force)) {
            $messages = $this->messages;
            $this->messages = null;

            return $messages;
        }

        $remaining = new \SplObjectStorage();
        $flushable = new \SplObjectStorage();

        foreach ($this->messages as $handler) {
            if ($force <= $now - $this->messages[$handler]->queuedAt) {
                $flushable[$handler] = $this->messages[$handler];
            } else {
                $remaining[$handler] = $this->messages[$handler];
            }
        }

        $this->messages = $remaining->count() ? $remaining : null;

        return $flushable;
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
