<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Event;

use Symfony\Component\Messenger\Worker;

/**
 * Dispatched when a worker has been started.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
final class WorkerStartedEvent
{
    public function __construct(
        private Worker $worker,
        private ?float $deadline = null,
        private int $idleTimeout = 1000000,
    ) {
    }

    public function getWorker(): Worker
    {
        return $this->worker;
    }

    /**
     * Returns the absolute time (as a microtime(true) float) at which the
     * worker must stop, or null if no --time-limit was set.
     */
    public function getDeadline(): ?float
    {
        return $this->deadline;
    }

    /**
     * Returns the duration in microseconds the worker sleeps between two
     * idle polling cycles (i.e. when no messages are found).
     *
     * Defaults to 1 000 000 µs (1 second).  Corresponds to the
     * --sleep option of messenger:consume.
     */
    public function getIdleTimeout(): int
    {
        return $this->idleTimeout;
    }
}
