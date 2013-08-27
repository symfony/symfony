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

use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;

interface ProcessableInterface
{
    const ERR = 'err';
    const OUT = 'out';

    const STATUS_READY = 'ready';
    const STATUS_STARTED = 'started';
    const STATUS_STOPPING = 'stopping';
    const STATUS_TERMINATED = 'terminated';

    /**
     * Runs the process.
     *
     * The callback receives the type of output (out or err) and
     * some bytes from the output in real-time. It allows to have feedback
     * from the independent process during execution.
     *
     * The STDOUT and STDERR are also available after the process is finished
     * via the getOutput() and getErrorOutput() methods.
     *
     * @param callback|null $callback A PHP callback to run whenever there is some
     *                                output available on STDOUT or STDERR
     *
     * @return integer The exit status code
     *
     * @throws RuntimeException When process can't be launch or is stopped
     *
     * @api
     */
    public function run($callback = null);

    /**
     * Starts the process and returns after sending the STDIN.
     *
     * This method blocks until all STDIN data is sent to the process then it
     * returns while the process runs in the background.
     *
     * The termination of the process can be awaited with wait().
     *
     * The callback receives the type of output (out or err) and some bytes from
     * the output in real-time while writing the standard input to the process.
     * It allows to have feedback from the independent process during execution.
     * If there is no callback passed, the wait() method can be called
     * with true as a second parameter then the callback will get all data occurred
     * in (and since) the start call.
     *
     * @param callback|null $callback A PHP callback to run whenever there is some
     *                                output available on STDOUT or STDERR
     *
     * @return Process The process itself
     *
     * @throws RuntimeException When process can't be launch or is stopped
     * @throws RuntimeException When process is already running
     */
    public function start($callback = null);

    /**
     * Restarts the process.
     *
     * Be warned that the process is cloned before being started.
     *
     * @param callable $callback A PHP callback to run whenever there is some
     *                           output available on STDOUT or STDERR
     *
     * @return Process The new process
     *
     * @throws RuntimeException When process can't be launch or is stopped
     * @throws RuntimeException When process is already running
     *
     * @see start()
     */
    public function restart($callback = null);

    /**
     * Waits for the process to terminate.
     *
     * The callback receives the type of output (out or err) and some bytes
     * from the output in real-time while writing the standard input to the process.
     * It allows to have feedback from the independent process during execution.
     *
     * @param callback|null $callback A valid PHP callback
     *
     * @return integer The exitcode of the process
     *
     * @throws RuntimeException When process timed out
     * @throws RuntimeException When process stopped after receiving signal
     */
    public function wait($callback = null);

    /**
     * Sends a posix signal to the process.
     *
     * @param  integer $signal A valid posix signal (see http://www.php.net/manual/en/pcntl.constants.php)
     * @return Process
     *
     * @throws LogicException   In case the process is not running
     * @throws RuntimeException In case --enable-sigchild is activated
     * @throws RuntimeException In case of failure
     */
    public function signal($signal);

    /**
     * Checks if the process ended successfully.
     *
     * @return Boolean true if the process ended successfully, false otherwise
     *
     * @api
     */
    public function isSuccessful();

    /**
     * Checks if the process is currently running.
     *
     * @return Boolean true if the process is currently running, false otherwise
     */
    public function isRunning();

    /**
     * Checks if the process is currently stopping.
     *
     * @return Boolean true if the process is currently stopping, false otherwise
     */
    public function isStopping();

    /**
     * Checks if the process has been started with no regard to the current state.
     *
     * @return Boolean true if status is ready, false otherwise
     */
    public function isStarted();

    /**
     * Checks if the process is terminated.
     *
     * @return Boolean true if process is terminated, false otherwise
     */
    public function isTerminated();

    /**
     * Gets the process status.
     *
     * The status is one of: ready, started, terminated.
     *
     * @return string The current process status
     */
    public function getStatus();

    /**
     * Stops the process.
     *
     * @param integer|float $timeout The timeout in seconds
     * @param integer       $signal  A posix signal to send in case the process has not stop at timeout, default is SIGKILL
     *
     * @return integer The exit-code of the process
     *
     * @throws RuntimeException if the process got signaled
     */
    public function stop($timeout = 10, $signal = null);

    /**
     * Performs a check between the timeout definition and the time the process started.
     *
     * In case you run a background process (with the start method), you should
     * trigger this method regularly to ensure the process timeout
     *
     * @throws ProcessTimedOutException In case the timeout was reached
     */
    public function checkTimeout();
}
