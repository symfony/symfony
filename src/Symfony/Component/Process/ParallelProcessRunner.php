<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process;

use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Manages running processes in parallel.
 *
 * @author John Nickell <email@johnnickell.com>
 */
class ParallelProcessRunner implements ProcessRunnerInterface
{
    private $maxConcurrent;
    private $usleepDelay;
    private $queue;
    private $procs;

    /**
     * Constructor.
     *
     * @param int $maxConcurrent The max concurrent processes or 0 for no limit
     * @param int $usleepDelay   The number of microseconds to delay between process checks
     */
    public function __construct($maxConcurrent = 1, $usleepDelay = 1000)
    {
        $this->maxConcurrent = $maxConcurrent;
        $this->usleepDelay = $usleepDelay;
        $this->queue = new \SplQueue();
        $this->procs = array();
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        $this->stop();
    }

    /**
     * {@inheritdoc}
     */
    public function add(Process $process, callable $callback = null)
    {
        $this->queue->enqueue(array($process, $callback));
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->queue = new \SplQueue();
        $this->procs = array();
    }

    /**
     * {@inheritdoc}
     */
    public function run($errorBehavior = ProcessRunnerInterface::EXCEPTION_ON_ERROR)
    {
        while (!$this->queue->isEmpty()) {
            $this->init();
            $this->tick($errorBehavior);
        }

        while (count($this->procs)) {
            $this->tick($errorBehavior);
        }

        $this->clear();
    }

    /**
     * Starts a process if possible.
     */
    private function init()
    {
        if ($this->maxConcurrent !== 0 && count($this->procs) >= $this->maxConcurrent) {
            return;
        }

        list($process, $callback) = $this->queue->dequeue();

        $process->start($callback);

        $this->procs[$process->getPid()] = $process;
    }

    /**
     * Performs running checks on processes.
     *
     * @param int $errorBehavior The behavior when a process fails
     *
     * @throws ProcessFailedException When a process fails
     */
    private function tick($errorBehavior)
    {
        usleep($this->usleepDelay);
        foreach ($this->procs as $pid => $process) {
            $process->checkTimeout();
            if ($process->isRunning()) {
                continue;
            }
            if (!$process->isSuccessful() && $errorBehavior === ProcessRunnerInterface::EXCEPTION_ON_ERROR) {
                throw new ProcessFailedException($process);
            }
            unset($this->procs[$pid]);
        }
    }

    /**
     * Stop running processes.
     */
    private function stop()
    {
        foreach ($this->procs as $process) {
            $process->stop(0);
        }

        $this->clear();
    }
}
