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

use SplQueue;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\RuntimeException;

/**
 * Manages running multiple processes
 *
 * @author John Nickell <email@johnnickell.com>
 */
class ProcessManager
{
    const EXCEPTION_ON_ERROR = 0;
    const IGNORE_ON_ERROR = 1;

    /**
     * Max concurrent processes
     *
     * @var int
     */
    protected $maxConcurrent;

    /**
     * Delay used with usleep
     *
     * @var int
     */
    protected $usleepDelay;

    /**
     * Process queue
     *
     * @var SplQueue
     */
    protected $queue;

    /**
     * Active processes
     *
     * @var array
     */
    protected $procs;

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
        $this->queue = new SplQueue();
        $this->procs = [];
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        $this->stop();
    }

    /**
     * Attaches a process
     *
     * The callback receives the type of output (out or err) and some bytes from
     * the output in real-time while writing the standard input to the process.
     * It allows to have feedback from the independent process during execution.
     *
     * @param Process       $process  The process
     * @param callable|null $callback A PHP callback to run whenever there is some
     *                                output available on STDOUT or STDERR
     */
    public function attach(Process $process, callable $callback = null)
    {
        $this->queue->enqueue([$process, $callback]);
    }

    /**
     * Clears attached processes
     */
    public function clear()
    {
        $this->queue = new SplQueue();
        $this->procs = [];
    }

    /**
     * Runs the attached processes
     *
     * @param int $errorBehavior The behavior when a process fails
     *
     * @throws RuntimeException When a process can't be launched
     * @throws RuntimeException When a process stopped after receiving signal
     * @throws RuntimeException When a process fails, depending on error behavior
     * @throws LogicException   In case a callback is provided and output has been disabled
     */
    public function run($errorBehavior = self::EXCEPTION_ON_ERROR)
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
     * Starts a process if possible
     */
    protected function init()
    {
        if ($this->maxConcurrent === 0 || count($this->procs) < $this->maxConcurrent) {
            $next = $this->queue->dequeue();
            /** @var Process $process */
            $process = $next[0];
            /** @var callable|null $callback */
            $callback = $next[1];

            $process->start($callback);

            $this->procs[$process->getPid()] = $process;
        }
    }

    /**
     * Performs running checks on processes
     *
     * @param int $errorBehavior The behavior when a process fails
     *
     * @throws ProcessFailedException When a process fails
     */
    protected function tick($errorBehavior)
    {
        usleep($this->usleepDelay);
        foreach ($this->procs as $pid => $process) {
            $process->checkTimeout();
            if (!$process->isRunning()) {
                if (!$process->isSuccessful() && $errorBehavior === self::EXCEPTION_ON_ERROR) {
                    throw new ProcessFailedException($process);
                }
                unset($this->procs[$pid]);
            }
        }
    }

    /**
     * Stop running processes
     */
    protected function stop()
    {
        foreach ($this->procs as $proc) {
            $proc->stop();
        }

        $this->clear();
    }
}
