<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Manager;

use Symfony\Component\Process\ProcessableInterface;
use Symfony\Component\Process\Exception\InvalidArgumentException;
use Symfony\Component\Process\Exception\RuntimeException;

/**
 * Wrapper for ProcessableInterface used by the ProcessManager to manage
 * subprocesses.
 *
 * @author Romain Neutron <imprec@gmail.com>
 */
class ManagedProcess implements ProcessableInterface
{
    /** @var ProcessableInterface */
    private $process;
    /** @var integer */
    private $executions;
    /** @var integer */
    private $initialExecutions;
    /** @var array */
    private $failures = array();
    /** @var Boolean */
    private $hasRun = false;

    public function __construct(ProcessableInterface $process, $executions = 1)
    {
        $this->process = $process;
        $this->setExecutions($executions);
    }

    public function __clone()
    {
        $this->process = clone $this->process;
    }

    /**
     * Gets the remaining number of executions.
     *
     * @return integer
     */
    public function getExecutions()
    {
        return $this->executions;
    }

    /**
     * Sets the number of executions.
     *
     * @param integer $executions
     *
     * @return ManagedProcess
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function setExecutions($executions)
    {
        if ($this->hasRun) {
            throw new RuntimeException('Can not modify executions once the process started. Reset the process before modifying this.');
        }

        if ($executions < 1) {
            throw new InvalidArgumentException('Executions must be a positive value.');
        }

        $this->executions = $this->initialExecutions = $executions;

        return $this;
    }

    /**
     * Retries the last execution.
     *
     * @return ManagedProcess
     */
    public function retry()
    {
        if (!$this->hasRun) {
            throw new RuntimeException('Process must have run at least once to retry.');
        }

        $this->executions++;

        return $this;
    }

    /**
     * Resets the process.
     *
     * @return ManagedProcess
     */
    public function reset()
    {
        $this->hasRun = false;
        $this->executions = $this->initialExecutions;
        $this->failures = array();

        return $this;
    }

    /**
     * Checks if the process has run.
     *
     * @return Boolean
     */
    public function hasRun()
    {
        return $this->hasRun;
    }

    /**
     * Checks if the process can still run.
     *
     * If the number of remaining executions is 0, it returns false.
     *
     * @return Boolean
     */
    public function canRun()
    {
        return $this->executions > 0;
    }

    /**
     * Adds an execution failure.
     *
     * @param \Exception $e
     *
     * @return ManagedProcess
     */
    public function addFailure(\Exception $e)
    {
        $this->failures[] = $e;

        return $this;
    }

    /**
     * Returns execution failures.
     * 
     * @return \Exception[]
     */
    public function getFailures()
    {
        return $this->failures;
    }

    /**
     * Returns the managed process.
     *
     * @return ProcessableInterface
     */
    public function getManagedProcess()
    {
        return $this->process;
    }

    /**
     * {@inheritdoc}
     */
    public function run($callback = null)
    {
        if (!$this->canRun()) {
            throw new RuntimeException('No remaining executions for the managed process.');
        }

        $this->hasRun = true;
        $this->executions--;

        return $this->process->run($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function start($callback = null)
    {
        if (!$this->canRun()) {
            throw new RuntimeException('No remaining executions for the managed process.');
        }

        $this->hasRun = true;
        $this->process->start($callback);
        $this->executions--;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function restart($callback = null)
    {
        $this->hasRun = true;
        $process = clone $this;
        $process->setManagedProcess($this->process->restart($callback));

        return $process;
    }

    /**
     * {@inheritdoc}
     */
    public function wait($callback = null)
    {
        return $this->process->wait($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function signal($signal)
    {
        $this->process->signal($signal);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isSuccessful()
    {
        return $this->process->isSuccessful();
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning()
    {
        return $this->process->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function isStopping()
    {
        return $this->process->isStopping();
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return $this->process->isStarted();
    }

    /**
     * {@inheritdoc}
     */
    public function isTerminated()
    {
        return $this->process->isTerminated();
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus()
    {
        return $this->process->getStatus();
    }

    /**
     * {@inheritdoc}
     */
    public function stop($timeout = 10, $signal = null)
    {
        return $this->process->stop($timeout, $signal);
    }

    /**
     * {@inheritdoc}
     */
    public function checkTimeout()
    {
        $this->process->checkTimeout();
    }

    /**
     * Sets the managed process
     *
     * @param ProcessableInterface $process
     *
     * @return ManagedProcess
     */
    private function setManagedProcess(ProcessableInterface $process)
    {
        $this->process = $process;

        return $this;
    }
}
