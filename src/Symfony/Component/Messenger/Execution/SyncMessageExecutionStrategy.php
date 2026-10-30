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
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\FlushBatchHandlersStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;

final class SyncMessageExecutionStrategy implements MessageExecutionStrategyInterface
{
    private ?array $heldAcks = null;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly \Closure $onAcknowledge,
    ) {
    }

    public function execute(Envelope $envelope, string $transportName, callable $onHandled): void
    {
        $acked = false;
        $acks = [];
        $dispatching = true;
        $batch = null;
        $flushes = [];
        $combine = static function (array $acks) use (&$batch, &$acked): ?array {
            if ($ack = DeferredBatchMessageQueue::combineAcks($batch, $acks)) {
                $acked = true;
            }

            return $ack;
        };

        $ack = function (Envelope $handledEnvelope, ?\Throwable $handledError = null) use (&$acks, &$dispatching, $combine, $transportName): void {
            if ($dispatching) {
                $acks[] = [$handledEnvelope, $handledError];

                return;
            }

            if (null !== $this->heldAcks) {
                $this->heldAcks[] = [$transportName, $handledEnvelope, $handledError, $combine];
            } else {
                ($this->onAcknowledge)($transportName, $handledEnvelope, $handledError, $combine);
            }
        };

        $deferFlush = static function (HandlerDescriptor $handlerDescriptor) use (&$flushes): void {
            $flushes[] = new NoAutoAckStamp($handlerDescriptor);
        };

        $this->heldAcks = [];

        try {
            $envelope = $this->bus->dispatch($envelope->with(new AckStamp($ack, $deferFlush)));
        } catch (\Throwable $e) {
            $this->releaseHeldAcks(true);

            if ($e instanceof HandlerFailedException && $e->getEnvelope()->last(NoAutoAckStamp::class)) {
                $acks = [];
                $e = $this->flushBatchHandlers($e, $acks);
            }

            // when the bus throws, its exception wins over the acks sent during the dispatch
            $dispatching = false;
            $onHandled($envelope, $transportName, $acked, $e);

            return;
        }

        $this->releaseHeldAcks(false);
        $dispatching = false;

        if ($acks || $envelope->last(NoAutoAckStamp::class)) {
            $batch = [$envelope->withoutAll(AckStamp::class), []];

            if ($batchAck = $combine(array_map(static fn (array $ack) => [$transportName, ...$ack], $acks))) {
                ($this->onAcknowledge)(...$batchAck);
            }
        }

        // the ack closure references this list, so emptying it frees the envelopes without waiting for the garbage collector
        $acks = [];

        if ($flushes) {
            $this->heldAcks = [];
            try {
                $this->bus->dispatch($envelope->withoutAll(AckStamp::class)->withoutAll(NoAutoAckStamp::class)->with(new FlushBatchHandlersStamp(true), ...$flushes));
                $this->releaseHeldAcks(false);
            } catch (\Throwable) {
                // the messages that the batch didn't settle wait for the next flush of the worker
                $this->releaseHeldAcks(true);
            }
        }

        $onHandled($envelope, $transportName, $acked, null);
    }

    public function shouldPauseConsumption(): bool
    {
        return false;
    }

    public function wait(callable $onHandled, ?float $timeout = null): bool
    {
        return false;
    }

    public function flush(callable $onHandled, bool|float $force = false): bool
    {
        return false;
    }

    public function shutdown(): void
    {
    }

    /**
     * Flushes the batch handlers that still have a failed message in their batch, in a dispatch of their own.
     *
     * Their work for other messages then doesn't depend on the failed dispatch, and their outcome joins the failure.
     */
    private function flushBatchHandlers(HandlerFailedException $e, array &$acks): HandlerFailedException
    {
        $envelope = $e->getEnvelope();
        $exceptions = $e->getWrappedExceptions();

        $this->heldAcks = [];
        try {
            $this->bus->dispatch($envelope->withoutAll(AckStamp::class)->with(new FlushBatchHandlersStamp(true)));
            $this->releaseHeldAcks(false);
        } catch (\Throwable $flushError) {
            // the failed flush may have undone what the batch did, so a retry runs it again
            $this->releaseHeldAcks(true);
            $acks = [];
            $exceptions += $flushError instanceof HandlerFailedException ? $flushError->getWrappedExceptions() : [$flushError];
        }

        $pending = array_map(static fn (NoAutoAckStamp $stamp) => $stamp->getHandlerDescriptor()->getName(), $envelope->all(NoAutoAckStamp::class));
        $stamps = array_filter($envelope->all(HandledStamp::class), static fn (HandledStamp $stamp) => !\in_array($stamp->getHandlerName(), $pending, true));

        foreach ($acks as [$ackEnvelope, $ackError]) {
            if (null === $ackError) {
                $stamps[] = $ackEnvelope->last(HandledStamp::class);
            } elseif ($ackError instanceof HandlerFailedException) {
                $exceptions += $ackError->getWrappedExceptions();
            }
        }

        return new HandlerFailedException($envelope->withoutAll(HandledStamp::class)->with(...$stamps), $exceptions);
    }

    /**
     * Releases the acks that other messages sent during a dispatch, as failures when that dispatch failed.
     *
     * Their batch was flushed during the failed dispatch, so what the batch did for them may have been undone.
     */
    private function releaseHeldAcks(bool $dispatchFailed): void
    {
        $acks = $this->heldAcks;
        $this->heldAcks = null;

        foreach ($acks as [$transportName, $envelope, $e, $combine]) {
            if ($dispatchFailed && null === $e && $stamp = $envelope->last(HandledStamp::class)) {
                $stamps = $envelope->all(HandledStamp::class);
                array_pop($stamps);
                $envelope = $envelope->withoutAll(HandledStamp::class)->with(...$stamps);
                $e = new HandlerFailedException($envelope, [$stamp->getHandlerName() => new RuntimeException(\sprintf('The batch of "%s" was flushed during the dispatch of another message, and that dispatch failed.', $stamp->getHandlerName()))]);
            }

            ($this->onAcknowledge)($transportName, $envelope, $e, $combine);
        }
    }
}
