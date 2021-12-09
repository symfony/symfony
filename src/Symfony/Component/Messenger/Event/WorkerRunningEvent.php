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
 * Dispatched after the worker processed a message or didn't receive a message at all.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
final class WorkerRunningEvent
{
    private Worker $worker;
    private bool $isWorkerIdle;

    public function __construct(Worker $worker, bool $isWorkerIdle)
    {
        $this->worker = $worker;
        $this->isWorkerIdle = $isWorkerIdle;
    }

    public function getWorker(): Worker
    {
        return $this->worker;
    }

    /**
     * Returns true when no message has been received by the worker.
     */
    public function isWorkerIdle(): bool
    {
        return $this->isWorkerIdle;
    }
}
