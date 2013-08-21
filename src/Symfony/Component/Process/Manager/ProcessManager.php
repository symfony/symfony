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

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\ProcessableInterface;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\InvalidArgumentException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\Exception\ProcessManagerException;

/**
 * ProcessManager runs multiple processes parallely. 
 *
 * @author Romain Neutron <imprec@gmail.com>
 * 
 * @api
 */
class ProcessManager implements ProcessableInterface, \Countable
{
    const STRATEGY_ABORT = 0;
    const STRATEGY_IGNORE = 1;
    const STRATEGY_RETRY = 2;

    /** @var Boolean */
    private $isDaemon;
    /** @var null|LoggerInterface */
    private $logger;
    /** @var string */
    private $status;
    /** @var integer */
    private $maxParallel;
    /** @var integer */
    private $timeoutStrategy;
    /** @var integer */
    private $failureStrategy;
    /** @var ManagedProcess[] */
    private $processes = array();

    public function __construct(LoggerInterface $logger = null, $maxParallel = null, $timeoutStrategy = self::STRATEGY_ABORT, $failureStrategy = self::STRATEGY_ABORT)
    {
        $this->isDaemon = false;
        $this->logger = $logger;
        $this->status = static::STATUS_READY;
        $this->setMaxParallelProcesses($maxParallel);
        $this->failureStrategy = $this->validateStrategy($failureStrategy);
        $this->timeoutStrategy = $this->validateStrategy($timeoutStrategy);
    }

    public function __destruct()
    {
        $this->stop();
    }

    /**
     * Sets the process manager behavior as daemon or not.
     *
     * @param Boolean $isDaemon
     *
     * @return ProcessManager
     */
    public function setDaemon($isDaemon)
    {
        $this->isDaemon = (Boolean) $isDaemon;

        return $this;
    }

    /**
     * Returns true if the process manager behaves as a daemon.
     *
     * In case it is, the manager will not stop until the stop method is
     * explicitely called.
     *
     * @return Boolean
     */
    public function isDaemon()
    {
        return $this->isDaemon;
    }

    /**
     * {@inheritdoc}
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Returns the attached logger.
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->processes);
    }

    /**
     * Returns the managed processes.
     *
     * @return ManagedProcess[]
     */
    public function getManagedProcesses()
    {
        return $this->processes;
    }

    /**
     * Sets the managed processes.
     *
     * @param array $processes An array of ManagedProcess instances.
     *
     * @return ProcessManager
     *
     * @throws ProcessManagerException
     */
    public function setManagedProcesses(array $processes)
    {
        if ($this->isRunning()) {
            throw new ProcessManagerException('Can not set processes while running.');
        }

        foreach (array_keys($this->processes) as $name) {
            $this->remove($name);
        }

        foreach ($processes as $name => $process) {
            $this->attach($name, $process);
        }

        return $this;
    }

    /**
     * Gets the timeout strategy.
     *
     * @return integer One of the ProcessManager::STRATEGY_* constant
     */
    public function getTimeoutStrategy()
    {
        return $this->timeoutStrategy;
    }

    /**
     * Sets the timeout strategy.
     *
     * This is used in case of the timeout of one of the managed process.
     * If strategy is set to ProcessManager::STRATEGY_ABORT, all processes are
     * stopped in case of a process timeout.
     * If strategy is set to ProcessManager::STRATEGY_IGNORE, the timeout is
     * ignored, remaining processes are run.
     * If strategy is set to ProcessManager::STRATEGY_RETRY, the timed-out
     * process is run again until success.
     *
     * @param integer $strategy One of the ProcessManager::STRATEGY_* constant
     *
     * @return ProcessManager
     *
     * @throws InvalidArgumentException In case the strategy is not valid
     */
    public function setTimeoutStrategy($strategy)
    {
        $this->timeoutStrategy = $this->validateStrategy($strategy);

        return $this;
    }

    /**
     * Gets the failure strategy.
     *
     * @return integer One of the ProcessManager::STRATEGY_* constant
     */
    public function getFailureStrategy()
    {
        return $this->failureStrategy;
    }

    /**
     * Sets the failure strategy.
     *
     * This is used in case of the failure of one of the managed process.
     * If strategy is set to ProcessManager::STRATEGY_ABORT, all processes are
     * stopped in case of a process failure.
     * If strategy is set to ProcessManager::STRATEGY_IGNORE, the failure is
     * ignored, remaining processes are run.
     * If strategy is set to ProcessManager::STRATEGY_RETRY, the failing
     * process is run again until success.
     *
     * @param integer $strategy One of the ProcessManager::STRATEGY_* constant
     *
     * @return ProcessManager
     *
     * @throws InvalidArgumentException In case the strategy is not valid
     */
    public function setFailureStrategy($strategy)
    {
        $this->failureStrategy = $this->validateStrategy($strategy);

        return $this;
    }

    /**
     * Gets the maximum number of processes that can be run in parallel.
     *
     * @return integer
     */
    public function getMaxParallelProcesses()
    {
        return $this->maxParallel;
    }

    /**
     * Sets the maximum number of processes that can be run in parallel.
     *
     * @param integer $maxParallel
     *
     * @return ProcessManager
     *
     * @throws InvalidArgumentException In case of invalid value
     */
    public function setMaxParallelProcesses($maximum)
    {
        if (null !== $maximum && 1 > $maximum) {
            throw new InvalidArgumentException('Max parallel processes must be a positive value.');
        }

        $this->maxParallel = null === $maximum ? INF : (integer) $maximum;

        return $this;
    }

    /**
     * Checks if the given name refers to a managed process.
     *
     * @param string $name
     *
     * @return Boolean
     */
    public function has($name)
    {
        return isset($this->processes[$name]);
    }

    /**
     * Adds a process to the managed ones.
     *
     * @param ProcessableInterface $process
     * @param string               $name       A unique name to register the process. If not provided, a name will be generated.
     * @param integer              $executions
     *
     * @return ProcessManager
     *
     * @throws InvalidArgumentException
     */
    public function add(ProcessableInterface $process, $name = null, $executions = 1)
    {
        if (null === $name) {
            $name = $this->generateProcessName();
        } elseif ($this->has($name)) {
            throw new InvalidArgumentException(sprintf('A process named %s is already attached.', $name));
        }

        $this->attach($name, new ManagedProcess($process, $executions));

        return $this;
    }

    /**
     * Removes a Process from the manager given its name.
     *
     * @param string  $name    The name of the managed process.
     * @param integer $timeout If the process is still running, the timeout to wait before sending a SIGKILL signal.
     * @param integer $signal  If the process is still running, the signal to send after the timeout is reached.
     *
     * @return ManagedProcess The removed process
     *
     * @throws InvalidArgumentException In case no process with the given name exists.
     */
    public function remove($name, $timeout = 10, $signal = null)
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('No process named %s is attached to the manager.', $name));
        }

        $process = $this->processes[$name];
        $process->stop($timeout, $signal);
        unset($this->processes[$name]);

        return $process;
    }

    /**
     * Returns a process given a name.
     *
     * @param string $name The name of the managed process
     *
     * @return ManagedProcess
     *
     * @throws InvalidArgumentException In case the process is unknown.
     */
    public function get($name)
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('No process named %s is attached to the manager.', $name));
        }

        return $this->processes[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function run($callback = null)
    {
        $this->start($callback);

        return $this->wait();
    }

    /**
     * {@inheritdoc}
     */
    public function start($callback = null)
    {
        if ($this->isRunning()) {
            throw new ProcessManagerException('Manager is already running.');
        }

        if (0 === count($this) && !$this->isDaemon) {
            throw new LogicException('No processes are currently managed.');
        }

        $this->status = static::STATUS_STARTED;
        $this->updateProcesses($callback);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function restart($callback = null)
    {
        $process = clone($this);
        $processes = array_map(function ($managed) {
            $managed = clone($managed);
            $managed->reset();

            return $managed;
        }, $process->getManagedProcesses());

        $process->setManagedProcesses($processes);

        return $process->start($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function wait($callback = null)
    {
        while ($this->status !== static::STATUS_TERMINATED) {
            $this->updateProcesses($callback);
            usleep(10000);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function signal($signal)
    {
        if (!$this->isRunning()) {
            throw new LogicException('Can not send signal on a non running process.');
        }

        foreach ($this->processes as $name => $process) {
            if ($process->isRunning()) {
                $this->log('debug', sprintf('Signaling process %s with signal "%d"', $name, $signal));
                $process->signal($signal);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isSuccessful()
    {
        if (count($this) === 0) {
            throw new LogicException('No processes are currently managed.');
        }

        foreach ($this->processes as $process) {
            if (!$process->isSuccessful()) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning()
    {
        $this->updateProcesses();

        return in_array($this->status, array(static::STATUS_STARTED, static::STATUS_STOPPING), true);
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return $this->status !== self::STATUS_READY;
    }

    /**
     * {@inheritdoc}
     */
    public function isTerminated()
    {
        $this->updateProcesses();

        return $this->status === self::STATUS_TERMINATED;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus()
    {
        $this->updateProcesses();

        return $this->status;
    }

    /**
     * {@inheritdoc}
     */
    public function stop($timeout = 10, $signal = null)
    {
        $timeout = microtime(true) + $timeout;
        $this->updateProcesses();

        if (static::STATUS_STARTED !== $this->status) {
            return;
        }

        $this->status = static::STATUS_STOPPING;

        if ($this->isRunning()) {
            $this->signal(null !== $signal ? $signal : (defined('SIGTERM') ? SIGTERM : 15));
        }

        do {
            usleep(1000);
            $running = $this->isRunning();
        } while (true === $running && microtime(true) < $timeout);

        if (true === $running) {
            foreach ($this->processes as $name => $process) {
                if ($process->isRunning()) {
                    $this->log('info', sprintf('Stopping process %s', $name));
                    $process->stop(0, $signal);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function checkTimeout()
    {
        $this->updateProcesses();
    }

    /**
     * Updates the managed process given the current status
     *
     * @param mixed $callback A callback to pass to starting processes.
     *
     * @return ProcessManager
     */
    private function updateProcesses($callback = null)
    {
        if (!in_array($this->status, array(static::STATUS_STARTED, static::STATUS_STOPPING), true)) {
            return;
        }

        $concurrent = 0;
        $canRun = true === $this->isDaemon && static::STATUS_STARTED === $this->status;

        foreach ($this->processes as $name => $process) {
            if ($concurrent >= $this->maxParallel) {
                break;
            }

            if ($process->isRunning()) {
                if ($this->doExecute($process, 'checkTimeout', array(), 'Managed process timed-out.')) {
                    $concurrent++;
                } else {
                    $this->log('info', sprintf('Process %s timed-out.', $name));
                }
            }

            if (!$process->isRunning()) {
                if ($process->hasRun() && !$process->isSuccessful()) {
                    $this->addFailureToProcess($process, new ProcessFailedException($process->getManagedProcess()), $this->failureStrategy);
                    $this->log('error', sprintf('Process %s failed. (failure #%d)', $name, count($process->getFailures())));
                }
                if (static::STATUS_STOPPING !== $this->status && $process->canRun()) {
                    $this->doExecute($process, 'start', array($callback), 'Unable to start the managed process.');
                    $concurrent++;
                    $this->log('notice', sprintf('Process %s started.', $name));
                }
            }

            $canRun = $canRun && $process->canRun();
        }

        if (0 === $concurrent && false === $canRun) {
            $this->status = static::STATUS_TERMINATED;
        }

        if (static::STATUS_STARTED === $this->status) {
            $this->log('debug', sprintf('Currently running %d concurrent processes.', $concurrent));
        }

        return $this;
    }

    /**
     * Executes a method against a process.
     *
     * @param ManagedProcess $process  The managed process on which the method is called.
     * @param string         $method   The name of the method to call.
     * @param array          $args     The arguments to pass to the method call.
     * @param string         $errorMsg The message of the exception and the logger in case of failure.
     *
     * @return Booleans True if the method call was successful, false otherwise.
     */
    private function doExecute(ManagedProcess $process, $method, $args = array(), $errorMsg = '')
    {
        try {
            call_user_func_array(array($process, $method), $args);

            return true;
        } catch (ProcessTimedOutException $e) {
            $this->handleException($process, $e, $errorMsg, $this->timeoutStrategy);
        } catch (ProcessException $e) {
            $this->handleException($process, $e, $errorMsg, $this->failureStrategy);
        }

        return false;
    }

    /**
     * Handles a managed process exception, given the current configuration.
     *
     * @param ManagedProcess $process  The process that thrown the exception.
     * @param \Exception     $e        The exception to handle.
     * @param string         $errorMsg The error message
     * @param integer        $strategy The strategy related to the exception.
     *
     * @return ProcessManager
     *
     * @throws ProcessManagerException In case the strategy aborts the run.
     */
    private function handleException(ManagedProcess $process, \Exception $e, $errorMsg, $strategy)
    {
        $this->log('error', $errorMsg);

        if (static::STRATEGY_ABORT === $strategy) {
            $this->stop();
            throw new ProcessManagerException($errorMsg, $e->getCode(), $e);
        }
        $this->addFailureToProcess($process, $e, $strategy);

        return $this;
    }

    /**
     * Adds a failure to a process, reincrement depending on the strategy.
     *
     * @param ManagedProcess $process  The process
     * @param \Exception     $e        The failure to add
     * @param string         $strategy The strategy
     */
    private function addFailureToProcess($process, $e, $strategy)
    {
        $process->addFailure($e);
        if (static::STRATEGY_RETRY === $strategy) {
            $process->retry();
        }
    }

    /**
     * Sends a log message to the logger, if available.
     *
     * @param string $method  The logger method to use.
     * @param string $message The message to log.
     *
     * @return ProcessManager
     */
    private function log($method, $message)
    {
        if ($this->logger) {
            call_user_func(array($this->logger, $method), $message);
        }

        return $this;
    }

    /**
     * Validates a strategy value.
     *
     * @param integer $strategy The strategy to validate
     *
     * @return integer The validated strategy.
     *
     * @throws InvalidArgumentException In case the strategy is invalid.
     */
    private function validateStrategy($strategy)
    {
        if (!in_array($strategy, array(static::STRATEGY_ABORT, static::STRATEGY_IGNORE, static::STRATEGY_RETRY), true)) {
            throw new InvalidArgumentException('Invalid strategy.');
        }

        return $strategy;
    }

    /**
     * Generates a process name.
     *
     * @return string
     */
    private function generateProcessName()
    {
        $n = count($this);
        do {
            $name = 'process #' . $n;
            $n++;
        } while ($this->has($name));

        return $name;
    }

    /**
     * Attaches a managed process.
     *
     * @param string         $name
     * @param ManagedProcess $process
     *
     * @return ProcessManager
     */
    private function attach($name, ManagedProcess $process)
    {
        $this->processes[$name] = $process;

        return $this;
    }
}
