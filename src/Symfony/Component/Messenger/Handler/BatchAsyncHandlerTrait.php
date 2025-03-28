<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler;

use Amp\Future;
use Symfony\Component\Messenger\Stamp\FutureStamp;
use Symfony\Component\Messenger\ParallelMessageBus;
use Symfony\Component\Messenger\Envelope;

/**
 * A batch handler trait designed for parallel execution using ParallelMessageBus.
 *
 * This trait collects jobs in worker-specific batches and processes them
 * in parallel by dispatching each job individually through ParallelMessageBus.
 */
trait BatchAsyncHandlerTrait
{
    /** @var array<string,array> Map of worker IDs to their job batches */
    private array $workerJobs = [];

    /** @var ParallelMessageBus|null */
    private ?ParallelMessageBus $parallelBus = null;

    /**
     * Set the parallel message bus to use for dispatching jobs.
     */
    public function setParallelMessageBus(ParallelMessageBus $bus): void
    {
        $this->parallelBus = $bus;
    }

    public function flush(bool $force): void
    {
        $workerId = $this->getCurrentWorkerId();

        if (isset($this->workerJobs[$workerId]) && $jobs = $this->workerJobs[$workerId]) {
            $this->workerJobs[$workerId] = [];

            if ($this->parallelBus) {
                // Process each job in parallel using ParallelMessageBus
                $futures = [];

                foreach ($jobs as [$message, $ack]) {
                    // Dispatch each message individually
                    $envelope = $this->parallelBus->dispatch($message);

                    $futureStamp = $envelope->last(FutureStamp::class);
                    if ($futureStamp) {
                        /** @var Future $future */
                        $future = $futureStamp->getFuture();
                        $futures[] = [$future, $ack];
                    }
                }

                // If force is true, wait for all results
                if ($force && $futures) {
                    foreach ($futures as [$future, $ack]) {
                        try {
                            $result = $future->await();
                            $ack->ack($result);
                        } catch (\Throwable $e) {
                            $ack->nack($e);
                        }
                    }
                }
            } else {
                // Fallback to synchronous processing
                $this->process($jobs);
            }
        }
    }

    /**
     * @param Acknowledger|null $ack The function to call to ack/nack the $message.
     *
     * @return mixed The number of pending messages in the batch if $ack is not null,
     *               the result from handling the message otherwise
     */
    private function handle(object $message, ?Acknowledger $ack): mixed
    {
        $workerId = $this->getCurrentWorkerId();

        if (!isset($this->workerJobs[$workerId])) {
            $this->workerJobs[$workerId] = [];
        }

        if (null === $ack) {
            $ack = new Acknowledger(get_debug_type($this));
            $this->workerJobs[$workerId][] = [$message, $ack];
            $this->flush(true);

            return $ack->getResult();
        }

        $this->workerJobs[$workerId][] = [$message, $ack];
        if (!$this->shouldFlush()) {
            return \count($this->workerJobs[$workerId]);
        }

        $this->flush(true);

        return 0;
    }

    private function shouldFlush(): bool
    {
        $workerId = $this->getCurrentWorkerId();
        return $this->getBatchSize() <= \count($this->workerJobs[$workerId] ?? []);
    }

    /**
     * Generates a unique identifier for the current worker context.
     */
    private function getCurrentWorkerId(): string
    {
        // In a worker pool, each worker has a unique ID
        return getmypid() ?: 'default-worker';
    }

    /**
     * Cleans up worker-specific resources when a worker completes its job.
     */
    public function cleanupWorker(): void
    {
        $workerId = $this->getCurrentWorkerId();

        // Flush any remaining jobs before cleaning up
        if (isset($this->workerJobs[$workerId]) && !empty($this->workerJobs[$workerId])) {
            $this->flush(true);
        }

        unset($this->workerJobs[$workerId]);
    }

    /**
     * Completes the jobs in the list.
     * This is used as a fallback when ParallelMessageBus is not available.
     *
     * @param list<array{0: object, 1: Acknowledger}> $jobs A list of pairs of messages and their corresponding acknowledgers
     */
    abstract private function process(array $jobs): void;

    private function getBatchSize(): int
    {
        return 10;
    }
}