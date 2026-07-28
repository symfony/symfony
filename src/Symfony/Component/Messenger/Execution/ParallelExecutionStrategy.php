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

use Amp\CancelledException;
use Amp\Future;
use Amp\Parallel\Worker\ContextWorkerFactory;
use Amp\Parallel\Worker\Worker;
use Amp\Sync\Channel;
use Amp\TimeoutCancellation;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Execution\Message\DeferredEnvelopeMessage;
use Symfony\Component\Messenger\Execution\Message\DispatchEnvelopeMessage;
use Symfony\Component\Messenger\Execution\Message\FlushBatchHandlersMessage;
use Symfony\Component\Messenger\Execution\Message\HandledEnvelopeMessage;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

use function Amp\async;
use function Amp\Future\await;
use function Amp\Future\awaitFirst;

/**
 * Runs messages on a pool of workers, one message at a time per worker.
 *
 * Batch handlers accumulate state in the worker that holds them, so all messages sharing
 * an affinity key (bus + transport + message class) must reach that same worker. Such keys
 * are therefore pinned to one channel and process serially: their throughput scales with
 * the number of distinct batching keys in flight, not with the concurrency limit.
 * Non-batching keys are free to use any available worker.
 */
final class ParallelExecutionStrategy implements MessageExecutionStrategyInterface
{
    private const KEY_MODE_NON_BATCHING = 1;
    private const KEY_MODE_BATCHING = 2;

    private int $nextRequestId = 0;

    /** @var array<int, array<int, PendingParallelExecutionRequest>> */
    private array $pendingRequests = [];

    /** @var array<int, Channel> */
    private array $channels = [];

    /** @var array<string, self::KEY_MODE_*> */
    private array $keyModes = [];

    /** @var array<string, int> Batching keys, each pinned to the channel that owns its batch */
    private array $batchingKeyAffinities = [];

    /** @var array<int, true> */
    private array $busyChannels = [];

    /** @var array<int, Worker> */
    private array $workers = [];

    /** @var array<int, Future<array{int, mixed, ?\Throwable}>> */
    private array $receiveFutures = [];

    // whether a key batches is only known once a handler defers, so the first message of an
    // unknown key runs alone: spreading its siblings across channels first would strand a
    // batch on a worker that can never complete it
    private ?int $pendingProbeRequestId = null;

    public function __construct(
        private readonly string $console,
        private readonly int $concurrencyLimit = 1,
        private readonly int $resetInterval = 1,
        private readonly ?int $memoryLimit = null,
    ) {
    }

    public function execute(Envelope $envelope, string $transportName, callable $onHandled): void
    {
        try {
            $affinityKey = $this->getAffinityKey($envelope);
            $keyMode = $this->keyModes[$affinityKey] ?? null;
            $isProbe = null === $keyMode;
            $channel = $this->getChannel($affinityKey, $keyMode);
            $channelId = spl_object_id($channel);
            $requestId = ++$this->nextRequestId;
            $message = new DispatchEnvelopeMessage($requestId, $envelope);

            try {
                $channel->send($message);
            } catch (\Throwable $e) {
                $this->removeChannel($channelId, $onHandled, $e);
                $channel = $this->getChannel($affinityKey, $keyMode);
                $channelId = spl_object_id($channel);
                $channel->send($message);
            }

            $this->pendingRequests[$channelId][$requestId] = new PendingParallelExecutionRequest($transportName, $envelope, $channelId, $affinityKey, $isProbe);
            $this->busyChannels[$channelId] = true;

            if ($isProbe) {
                $this->pendingProbeRequestId = $requestId;
            }

            $this->armChannel($channelId);
            $this->drainReadyResponses($onHandled);
        } catch (\Throwable $e) {
            $acked = false;
            $onHandled($envelope, $transportName, $acked, $e);
        }
    }

    public function shouldPauseConsumption(): bool
    {
        return null !== $this->pendingProbeRequestId || \count($this->busyChannels) >= $this->concurrencyLimit;
    }

    /**
     * @phpstan-impure
     */
    public function wait(callable $onHandled): bool
    {
        while ($this->pendingRequests && $this->receiveFutures) {
            try {
                // the timeout is a heartbeat only: the event loop wakes us as soon as any
                // channel produces a response, there is no per-channel polling involved
                [$channelId, $response, $error] = awaitFirst($this->receiveFutures, new TimeoutCancellation(0.1));
            } catch (CancelledException) {
                continue;
            }

            unset($this->receiveFutures[$channelId]);

            if (null !== $error) {
                return $this->removeChannel($channelId, $onHandled, $error);
            }

            $handled = $this->handleResponse($channelId, $response, $onHandled);
            $this->armChannel($channelId);

            if ($handled) {
                return true;
            }
        }

        return false;
    }

    public function flush(callable $onHandled, bool|float $force = false): bool
    {
        $handled = $this->drainReadyResponses($onHandled);

        if (!$this->pendingRequests) {
            return $handled;
        }

        foreach ($this->channels as $channelId => $channel) {
            if (!isset($this->pendingRequests[$channelId])) {
                continue;
            }

            try {
                $channel->send(new FlushBatchHandlersMessage($force));
            } catch (\Throwable $e) {
                $handled = $this->removeChannel($channelId, $onHandled, $e) || $handled;
            }
        }

        $handled = $this->drainReadyResponses($onHandled) || $handled;

        if (true === $force) {
            while ($this->pendingRequests) {
                $handled = $this->wait($onHandled) || $handled;
            }
        }

        return $handled;
    }

    public function shutdown(): void
    {
        foreach ($this->channels as $channel) {
            try {
                $channel->send(null);
            } catch (\Throwable) {
            }
        }

        $shutdowns = [];
        foreach ($this->workers as $worker) {
            $shutdowns[] = async(static function () use ($worker): void {
                try {
                    $worker->shutdown();
                } catch (\Throwable) {
                    $worker->kill();
                }
            });
        }
        await($shutdowns);

        $this->workers = [];
        $this->channels = [];
        $this->receiveFutures = [];
    }

    private function getChannel(string $affinityKey, ?int $keyMode): Channel
    {
        $channelId = $this->batchingKeyAffinities[$affinityKey] ?? null;

        // a pinned key goes back to its channel even when that channel is busy, which is what
        // serializes batching keys; the pin is dropped only when the channel dies
        if (isset($channelId, $this->channels[$channelId])) {
            return $this->channels[$channelId];
        }

        if (\count($this->channels) < max(1, $this->concurrencyLimit)) {
            $channel = $this->createChannel();

            if (self::KEY_MODE_BATCHING === $keyMode) {
                $this->batchingKeyAffinities[$affinityKey] = spl_object_id($channel);
            }

            return $channel;
        }

        /** @var int $channelId At least one channel exists at this point */
        $channelId = array_key_first($this->channels);

        foreach ($this->channels as $id => $channel) {
            if (!isset($this->busyChannels[$id])) {
                $channelId = $id;
                break;
            }
        }

        if (self::KEY_MODE_BATCHING === $keyMode) {
            $this->batchingKeyAffinities[$affinityKey] = $channelId;
        }

        return $this->channels[$channelId];
    }

    private function createChannel(): Channel
    {
        $factory = new ContextWorkerFactory();
        $worker = $factory->create();

        $execution = $worker->submit(new DispatchTask($this->console, $this->resetInterval, $this->memoryLimit));
        $channel = $execution->getChannel();
        $channelId = spl_object_id($channel);
        $this->workers[$channelId] = $worker;
        $this->channels[$channelId] = $channel;

        return $channel;
    }

    private function armChannel(int $channelId): void
    {
        if (isset($this->receiveFutures[$channelId]) || !isset($this->pendingRequests[$channelId], $this->channels[$channelId])) {
            return;
        }

        $channel = $this->channels[$channelId];

        // the future must stay armed until it resolves: an abandoned receive would
        // consume a response that nobody reads; catching inside the coroutine also
        // guarantees the future always completes with a value and never goes unhandled
        $this->receiveFutures[$channelId] = async(static function () use ($channelId, $channel): array {
            try {
                return [$channelId, $channel->receive(), null];
            } catch (\Throwable $e) {
                return [$channelId, null, $e];
            }
        });
    }

    /**
     * @phpstan-impure
     */
    private function drainReadyResponses(callable $onHandled): bool
    {
        $handled = false;

        while ($this->receiveFutures) {
            try {
                // a zero timeout lets the event loop process buffered channel data
                // once without waiting for responses that are not there yet
                [$channelId, $response, $error] = awaitFirst($this->receiveFutures, new TimeoutCancellation(0));
            } catch (CancelledException) {
                return $handled;
            }

            unset($this->receiveFutures[$channelId]);

            if (null !== $error) {
                $handled = $this->removeChannel($channelId, $onHandled, $error) || $handled;

                continue;
            }

            $handled = $this->handleResponse($channelId, $response, $onHandled) || $handled;
            $this->armChannel($channelId);
        }

        return $handled;
    }

    private function handleResponse(int $channelId, mixed $response, callable $onHandled): bool
    {
        if (!$response instanceof DeferredEnvelopeMessage && !$response instanceof HandledEnvelopeMessage) {
            return false;
        }

        $requestId = $response->requestId;

        if (!isset($this->pendingRequests[$channelId][$requestId])) {
            return false;
        }

        $pendingRequest = $this->pendingRequests[$channelId][$requestId];

        if ($response instanceof DeferredEnvelopeMessage) {
            if ($pendingRequest->deferred) {
                return false;
            }

            // a deferred ack means a batch handler took the message: pin the key here for good
            $pendingRequest->deferred = true;
            $this->keyModes[$pendingRequest->affinityKey] = self::KEY_MODE_BATCHING;
            $this->batchingKeyAffinities[$pendingRequest->affinityKey] = $channelId;

            if ($pendingRequest->isProbe && $requestId === $this->pendingProbeRequestId) {
                $this->pendingProbeRequestId = null;
            }

            $this->recomputeBusyChannel($channelId);

            return true;
        }

        unset($this->pendingRequests[$channelId][$requestId]);

        if (!$this->pendingRequests[$channelId]) {
            unset($this->pendingRequests[$channelId], $this->busyChannels[$channelId]);
        } else {
            $this->recomputeBusyChannel($channelId);
        }

        if ($pendingRequest->isProbe && $requestId === $this->pendingProbeRequestId) {
            $this->pendingProbeRequestId = null;
        }

        $this->keyModes[$pendingRequest->affinityKey] ??= self::KEY_MODE_NON_BATCHING;

        // rebuild the envelope around the original message object so that identity-keyed
        // bookkeeping in the parent (e.g. Worker::$keepalives) keeps matching
        $handledEnvelope = Envelope::wrap($pendingRequest->envelope->getMessage(), array_merge(...array_values($response->envelope->all())));

        if (null !== $error = $response->error) {
            $handledEnvelope = ParallelExecutionFailureSanitizer::decorateFailedEnvelope($handledEnvelope);
            $error = ParallelExecutionFailureSanitizer::sanitizeError($error, $handledEnvelope);
        }

        $acked = false;
        $onHandled($handledEnvelope, $pendingRequest->transportName, $acked, $error);

        return true;
    }

    private function recomputeBusyChannel(int $channelId): void
    {
        unset($this->busyChannels[$channelId]);

        // a deferred request sits in a batch instead of occupying the worker, so it leaves the
        // channel free to accept the next message of its key and let the batch fill up
        foreach ($this->pendingRequests[$channelId] ?? [] as $request) {
            if (!$request->deferred) {
                $this->busyChannels[$channelId] = true;

                return;
            }
        }
    }

    /**
     * @phpstan-impure
     */
    private function removeChannel(int $channelId, callable $onHandled, ?\Throwable $previous = null): bool
    {
        $handled = false;

        unset($this->workers[$channelId], $this->channels[$channelId], $this->busyChannels[$channelId], $this->receiveFutures[$channelId]);
        $channelPendingRequests = $this->pendingRequests[$channelId] ?? [];
        unset($this->pendingRequests[$channelId]);

        foreach ($this->batchingKeyAffinities as $key => $affinityChannelId) {
            if ($channelId === $affinityChannelId) {
                unset($this->batchingKeyAffinities[$key]);
            }
        }

        foreach ($channelPendingRequests as $requestId => $pendingRequest) {
            if ($pendingRequest->isProbe && $requestId === $this->pendingProbeRequestId) {
                $this->pendingProbeRequestId = null;
            }

            $acked = false;
            $onHandled($pendingRequest->envelope, $pendingRequest->transportName, $acked, new \RuntimeException('The parallel worker stopped before returning a result.', 0, $previous));
            $handled = true;
        }

        return $handled;
    }

    private function getAffinityKey(Envelope $envelope): string
    {
        return ($envelope->last(BusNameStamp::class)?->getBusName() ?? 'message.bus').'|'.($envelope->last(ReceivedStamp::class)?->getTransportName() ?? '').'|'.$envelope->getMessage()::class;
    }
}
