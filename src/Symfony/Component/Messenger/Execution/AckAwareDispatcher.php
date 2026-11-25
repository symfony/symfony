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

/**
 * Dispatches a received message on a bus and takes care of the acks of its batch handlers.
 *
 * A batch handler acks the messages it holds when it flushes, usually during the dispatch of another message.
 * Those acks are held until that dispatch is done, and they fail with it when it fails. The dispatches running
 * in different fibers are independent: the acks sent during a dispatch are held for the fiber it runs in.
 *
 * Execution strategies use it to handle a message, and report the returned result themselves.
 *
 * @author Jonathan H. Wage
 */
final class AckAwareDispatcher
{
    /** @var \WeakMap<\Fiber, list<array>> */
    private \WeakMap $fiberHeldAcks;

    /** @var list<array>|null */
    private ?array $heldAcks = null;

    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
        $this->fiberHeldAcks = new \WeakMap();
    }

    /**
     * @param \Closure(string, Envelope, ?\Throwable, ?\Closure): void $onAcknowledge Receives the acks that the batch handlers send: the transport name, the envelope, the error and the closure combining the acks of the message
     * @param bool                                                     $acked         Set to whether the message got its outcome through the acks of its batch handlers.
     *                                                                                It MUST be the variable passed by reference to the $onHandled callable of the execution strategy:
     *                                                                                the batch handlers flip it later to signal a late ack, and passing a copy breaks the batch acking
     */
    public function dispatch(Envelope $envelope, string $transportName, \Closure $onAcknowledge, bool &$acked): DispatchResult
    {
        $acked = false;
        $acks = [];
        // the flag flips once the dispatch is done, while the ack closure below keeps living: it is read through a holder
        $dispatching = new \ArrayObject(['on' => true]);
        $batch = null;
        $flushes = [];
        $combine = static function (array $acks) use (&$batch, &$acked): ?array {
            if ($ack = DeferredBatchMessageQueue::combineAcks($batch, $acks)) {
                $acked = true;
            }

            return $ack;
        };

        $ack = function (Envelope $handledEnvelope, ?\Throwable $handledError = null) use (&$acks, $dispatching, $combine, $transportName, $onAcknowledge): void {
            if ($dispatching['on']) {
                $acks[] = [$handledEnvelope, $handledError];

                return;
            }

            if ($this->isHolding()) {
                $this->hold([$transportName, $handledEnvelope, $handledError, $combine, $onAcknowledge]);
            } else {
                $onAcknowledge($transportName, $handledEnvelope, $handledError, $combine);
            }
        };

        $deferFlush = static function (HandlerDescriptor $handlerDescriptor) use (&$flushes): void {
            $flushes[] = new NoAutoAckStamp($handlerDescriptor);
        };

        $this->startHolding();

        try {
            $envelope = $this->bus->dispatch($envelope->with(new AckStamp($ack, $deferFlush)));
        } catch (\Throwable $e) {
            $this->releaseHeldAcks(true);

            if ($e instanceof HandlerFailedException && $e->getEnvelope()->last(NoAutoAckStamp::class)) {
                $acks = [];
                $e = $this->flushBatchHandlers($e, $acks);
            }

            // when the bus throws, its exception wins over the acks sent during the dispatch
            $dispatching['on'] = false;

            return new DispatchResult($envelope, $e);
        }

        $this->releaseHeldAcks(false);
        $dispatching['on'] = false;

        if ($acks || $envelope->last(NoAutoAckStamp::class)) {
            $batch = [$envelope->withoutAll(AckStamp::class), []];

            if ($batchAck = $combine(array_map(static fn (array $ack) => [$transportName, ...$ack], $acks))) {
                $onAcknowledge(...$batchAck);
            }
        }

        // the ack closure references this list, so emptying it frees the envelopes without waiting for the garbage collector
        $acks = [];

        if ($flushes) {
            $this->startHolding();
            try {
                $this->bus->dispatch($envelope->withoutAll(AckStamp::class)->withoutAll(NoAutoAckStamp::class)->with(new FlushBatchHandlersStamp(true), ...$flushes));
                $this->releaseHeldAcks(false);
            } catch (\Throwable) {
                // the messages that the batch didn't settle wait for the next flush of the worker
                $this->releaseHeldAcks(true);
            }
        }

        return new DispatchResult($envelope, null);
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

        $this->startHolding();
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
        $acks = $this->stopHolding();

        foreach ($acks as [$transportName, $envelope, $e, $combine, $onAcknowledge]) {
            if ($dispatchFailed && null === $e && $stamp = $envelope->last(HandledStamp::class)) {
                $stamps = $envelope->all(HandledStamp::class);
                array_pop($stamps);
                $envelope = $envelope->withoutAll(HandledStamp::class)->with(...$stamps);
                $e = new HandlerFailedException($envelope, [$stamp->getHandlerName() => new RuntimeException(\sprintf('The batch of "%s" was flushed during the dispatch of another message, and that dispatch failed.', $stamp->getHandlerName()))]);
            }

            $onAcknowledge($transportName, $envelope, $e, $combine);
        }
    }

    private function isHolding(): bool
    {
        return null !== ($fiber = \Fiber::getCurrent()) ? isset($this->fiberHeldAcks[$fiber]) : null !== $this->heldAcks;
    }

    private function startHolding(): void
    {
        if ($fiber = \Fiber::getCurrent()) {
            $this->fiberHeldAcks[$fiber] = [];
        } else {
            $this->heldAcks = [];
        }
    }

    private function hold(array $ack): void
    {
        if ($fiber = \Fiber::getCurrent()) {
            $acks = $this->fiberHeldAcks[$fiber];
            $acks[] = $ack;
            $this->fiberHeldAcks[$fiber] = $acks;
        } else {
            $this->heldAcks[] = $ack;
        }
    }

    /**
     * @return list<array>
     */
    private function stopHolding(): array
    {
        if ($fiber = \Fiber::getCurrent()) {
            $acks = $this->fiberHeldAcks[$fiber] ?? [];
            unset($this->fiberHeldAcks[$fiber]);
        } else {
            $acks = $this->heldAcks ?? [];
            $this->heldAcks = null;
        }

        return $acks;
    }
}
