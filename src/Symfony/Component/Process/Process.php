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

use Symfony\Component\Process\Exception\InvalidArgumentException;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\RuntimeException;

/**
 * Process is a thin wrapper around proc_* functions to ease
 * start independent PHP processes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class Process
{
    const ERR = 'err';
    const OUT = 'out';

    const STATUS_READY = 'ready';
    const STATUS_STARTED = 'started';
    const STATUS_TERMINATED = 'terminated';

    const STDIN = 0;
    const STDOUT = 1;
    const STDERR = 2;

    // Timeout Precision in seconds.
    const TIMEOUT_PRECISION = 0.2;

    private $callback;
    private $commandline;
    private $cwd;
    private $env;
    private $stdin;
    private $starttime;
    private $timeout;
    private $options;
    private $exitcode;
    private $fallbackExitcode;
    private $processInformation;
    private $stdout;
    private $stderr;
    private $enhanceWindowsCompatibility;
    private $enhanceSigchildCompatibility;
    private $pipes;
    private $process;
    private $status = self::STATUS_READY;
    private $incrementalOutputOffset;
    private $incrementalErrorOutputOffset;
    private $tty;

    private $fileHandles;
    private $readBytes;

    private static $sigchild;

    /**
     * Exit codes translation table.
     *
     * User-defined errors must use exit codes in the 64-113 range.
     *
     * @var array
     */
    public static $exitCodes = array(
        0 => 'OK',
        1 => 'General error',
        2 => 'Misuse of shell builtins',

        126 => 'Invoked command cannot execute',
        127 => 'Command not found',
        128 => 'Invalid exit argument',

        // signals
        129 => 'Hangup',
        130 => 'Interrupt',
        131 => 'Quit and dump core',
        132 => 'Illegal instruction',
        133 => 'Trace/breakpoint trap',
        134 => 'Process aborted',
        135 => 'Bus error: "access to undefined portion of memory object"',
        136 => 'Floating point exception: "erroneous arithmetic operation"',
        137 => 'Kill (terminate immediately)',
        138 => 'User-defined 1',
        139 => 'Segmentation violation',
        140 => 'User-defined 2',
        141 => 'Write to pipe with no one reading',
        142 => 'Signal raised by alarm',
        143 => 'Termination (request to terminate)',
        // 144 - not defined
        145 => 'Child process terminated, stopped (or continued*)',
        146 => 'Continue if stopped',
        147 => 'Stop executing temporarily',
        148 => 'Terminal stop signal',
        149 => 'Background process attempting to read from tty ("in")',
        150 => 'Background process attempting to write to tty ("out")',
        151 => 'Urgent data available on socket',
        152 => 'CPU time limit exceeded',
        153 => 'File size limit exceeded',
        154 => 'Signal raised by timer counting virtual time: "virtual timer expired"',
        155 => 'Profiling timer expired',
        // 156 - not defined
        157 => 'Pollable event',
        // 158 - not defined
        159 => 'Bad syscall',
    );

    /**
     * Constructor.
     *
     * @param string  $commandline The command line to run
     * @param string  $cwd         The working directory
     * @param array   $env         The environment variables or null to inherit
     * @param string  $stdin       The STDIN content
     * @param integer $timeout     The timeout in seconds
     * @param array   $options     An array of options for proc_open
     *
     * @throws RuntimeException When proc_open is not installed
     *
     * @api
     */
    public function __construct($commandline, $cwd = null, array $env = null, $stdin = null, $timeout = 60, array $options = array())
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('The Process class relies on proc_open, which is not available on your PHP installation.');
        }

        $this->commandline = $commandline;
        $this->cwd = $cwd;

        // on windows, if the cwd changed via chdir(), proc_open defaults to the dir where php was started
        // on gnu/linux, PHP builds with --enable-maintainer-zts are also affected
        // @see : https://bugs.php.net/bug.php?id=51800
        // @see : https://bugs.php.net/bug.php?id=50524

        if (null === $this->cwd && (defined('ZEND_THREAD_SAFE') || defined('PHP_WINDOWS_VERSION_BUILD'))) {
            $this->cwd = getcwd();
        }
        if (null !== $env) {
            $this->setEnv($env);
        } else {
            $this->env = null;
        }
        $this->stdin = $stdin;
        $this->setTimeout($timeout);
        $this->enhanceWindowsCompatibility = true;
        $this->enhanceSigchildCompatibility = !defined('PHP_WINDOWS_VERSION_BUILD') && $this->isSigchildEnabled();
        $this->options = array_replace(array('suppress_errors' => true, 'binary_pipes' => true), $options);
    }

    public function __destruct()
    {
        // stop() will check if we have a process running.
        $this->stop();
    }

    public function __clone()
    {
        $this->resetProcessData();
    }

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
    public function run($callback = null)
    {
        $this->start($callback);

        return $this->wait();
    }

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
     * @throws RuntimeException When process can't be launch or is stopped
     * @throws RuntimeException When process is already running
     */
    public function start($callback = null)
    {
        if ($this->isRunning()) {
            throw new RuntimeException('Process is already running');
        }

        $this->resetProcessData();
        $this->starttime = microtime(true);
        $this->callback = $this->buildCallback($callback);
        $descriptors = $this->getDescriptors();

        $commandline = $this->commandline;

        if (defined('PHP_WINDOWS_VERSION_BUILD') && $this->enhanceWindowsCompatibility) {
            $commandline = 'cmd /V:ON /E:ON /C "'.$commandline.'"';
            if (!isset($this->options['bypass_shell'])) {
                $this->options['bypass_shell'] = true;
            }
        }

        $this->process = proc_open($commandline, $descriptors, $this->pipes, $this->cwd, $this->env, $this->options);

        if (!is_resource($this->process)) {
            throw new RuntimeException('Unable to launch a new process.');
        }
        $this->status = self::STATUS_STARTED;

        foreach ($this->pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $this->writePipes();
        $this->updateStatus(false);
        $this->checkTimeout();
    }

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
    public function restart($callback = null)
    {
        if ($this->isRunning()) {
            throw new RuntimeException('Process is already running');
        }

        $process = clone $this;
        $process->start($callback);

        return $process;
    }

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
    public function wait($callback = null)
    {
        $this->updateStatus(false);
        if (null !== $callback) {
            $this->callback = $this->buildCallback($callback);
        }
        while ($this->pipes || (defined('PHP_WINDOWS_VERSION_BUILD') && $this->fileHandles)) {
            $this->checkTimeout();
            $this->readPipes(true);
        }
        $this->updateStatus(false);
        if ($this->processInformation['signaled']) {
            if ($this->isSigchildEnabled()) {
                throw new RuntimeException('The process has been signaled.');
            }

            throw new RuntimeException(sprintf('The process has been signaled with signal "%s".', $this->processInformation['termsig']));
        }

        $time = 0;
        while ($this->isRunning() && $time < 1000000) {
            $time += 1000;
            usleep(1000);
        }

        if ($this->processInformation['signaled']) {
            if ($this->isSigchildEnabled()) {
                throw new RuntimeException('The process has been signaled.');
            }

            throw new RuntimeException(sprintf('The process has been signaled with signal "%s".', $this->processInformation['termsig']));
        }

        return $this->exitcode;
    }

    /**
     * Returns the Pid (process identifier), if applicable.
     *
     * @return integer|null The process id if running, null otherwise
     *
     * @throws RuntimeException In case --enable-sigchild is activated
     */
    public function getPid()
    {
        if ($this->isSigchildEnabled()) {
            throw new RuntimeException('This PHP has been compiled with --enable-sigchild. The process identifier can not be retrieved.');
        }

        $this->updateStatus(false);

        return $this->isRunning() ? $this->processInformation['pid'] : null;
    }

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
    public function signal($signal)
    {
        if (!$this->isRunning()) {
            throw new LogicException('Can not send signal on a non running process.');
        }

        if ($this->isSigchildEnabled()) {
            throw new RuntimeException('This PHP has been compiled with --enable-sigchild. The process can not be signaled.');
        }

        if (true !== @proc_terminate($this->process, $signal)) {
            throw new RuntimeException(sprintf('Error while sending signal `%d`.', $signal));
        }

        return $this;
    }

    /**
     * Returns the current output of the process (STDOUT).
     *
     * @return string The process output
     *
     * @api
     */
    public function getOutput()
    {
        $this->readPipes(false);

        return $this->stdout;
    }

    /**
     * Returns the output incrementally.
     *
     * In comparison with the getOutput method which always return the whole
     * output, this one returns the new output since the last call.
     *
     * @return string The process output since the last call
     */
    public function getIncrementalOutput()
    {
        $data = $this->getOutput();

        $latest = substr($data, $this->incrementalOutputOffset);
        $this->incrementalOutputOffset = strlen($data);

        return $latest;
    }

    /**
     * Returns the current error output of the process (STDERR).
     *
     * @return string The process error output
     *
     * @api
     */
    public function getErrorOutput()
    {
        $this->readPipes(false);

        return $this->stderr;
    }

    /**
     * Returns the errorOutput incrementally.
     *
     * In comparison with the getErrorOutput method which always return the
     * whole error output, this one returns the new error output since the last
     * call.
     *
     * @return string The process error output since the last call
     */
    public function getIncrementalErrorOutput()
    {
        $data = $this->getErrorOutput();

        $latest = substr($data, $this->incrementalErrorOutputOffset);
        $this->incrementalErrorOutputOffset = strlen($data);

        return $latest;
    }

    /**
     * Returns the exit code returned by the process.
     *
     * @return integer The exit status code
     *
     * @throws RuntimeException In case --enable-sigchild is activated and the sigchild compatibility mode is disabled
     *
     * @api
     */
    public function getExitCode()
    {
        if ($this->isSigchildEnabled() && !$this->enhanceSigchildCompatibility) {
            throw new RuntimeException('This PHP has been compiled with --enable-sigchild. You must use setEnhanceSigchildCompatibility() to use this method');
        }

        $this->updateStatus(false);

        return $this->exitcode;
    }

    /**
     * Returns a string representation for the exit code returned by the process.
     *
     * This method relies on the Unix exit code status standardization
     * and might not be relevant for other operating systems.
     *
     * @return string A string representation for the exit status code
     *
     * @see http://tldp.org/LDP/abs/html/exitcodes.html
     * @see http://en.wikipedia.org/wiki/Unix_signal
     */
    public function getExitCodeText()
    {
        $exitcode = $this->getExitCode();

        return isset(self::$exitCodes[$exitcode]) ? self::$exitCodes[$exitcode] : 'Unknown error';
    }

    /**
     * Checks if the process ended successfully.
     *
     * @return Boolean true if the process ended successfully, false otherwise
     *
     * @api
     */
    public function isSuccessful()
    {
        return 0 === $this->getExitCode();
    }

    /**
     * Returns true if the child process has been terminated by an uncaught signal.
     *
     * It always returns false on Windows.
     *
     * @return Boolean
     *
     * @throws RuntimeException In case --enable-sigchild is activated
     *
     * @api
     */
    public function hasBeenSignaled()
    {
        if ($this->isSigchildEnabled()) {
            throw new RuntimeException('This PHP has been compiled with --enable-sigchild. Term signal can not be retrieved');
        }

        $this->updateStatus(false);

        return $this->processInformation['signaled'];
    }

    /**
     * Returns the number of the signal that caused the child process to terminate its execution.
     *
     * It is only meaningful if hasBeenSignaled() returns true.
     *
     * @return integer
     *
     * @throws RuntimeException In case --enable-sigchild is activated
     *
     * @api
     */
    public function getTermSignal()
    {
        if ($this->isSigchildEnabled()) {
            throw new RuntimeException('This PHP has been compiled with --enable-sigchild. Term signal can not be retrieved');
        }

        $this->updateStatus(false);

        return $this->processInformation['termsig'];
    }

    /**
     * Returns true if the child process has been stopped by a signal.
     *
     * It always returns false on Windows.
     *
     * @return Boolean
     *
     * @api
     */
    public function hasBeenStopped()
    {
        $this->updateStatus(false);

        return $this->processInformation['stopped'];
    }

    /**
     * Returns the number of the signal that caused the child process to stop its execution.
     *
     * It is only meaningful if hasBeenStopped() returns true.
     *
     * @return integer
     *
     * @api
     */
    public function getStopSignal()
    {
        $this->updateStatus(false);

        return $this->processInformation['stopsig'];
    }

    /**
     * Checks if the process is currently running.
     *
     * @return Boolean true if the process is currently running, false otherwise
     */
    public function isRunning()
    {
        if (self::STATUS_STARTED !== $this->status) {
            return false;
        }

        $this->updateStatus(false);

        return $this->processInformation['running'];
    }

    /**
     * Checks if the process has been started with no regard to the current state.
     *
     * @return Boolean true if status is ready, false otherwise
     */
    public function isStarted()
    {
        return $this->status != self::STATUS_READY;
    }

    /**
     * Checks if the process is terminated.
     *
     * @return Boolean true if process is terminated, false otherwise
     */
    public function isTerminated()
    {
        $this->updateStatus(false);

        return $this->status == self::STATUS_TERMINATED;
    }

    /**
     * Gets the process status.
     *
     * The status is one of: ready, started, terminated.
     *
     * @return string The current process status
     */
    public function getStatus()
    {
        $this->updateStatus(false);

        return $this->status;
    }

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
    public function stop($timeout = 10, $signal = null)
    {
        $timeoutMicro = microtime(true) + $timeout;
        if ($this->isRunning()) {
            proc_terminate($this->process);
            do {
                usleep(1000);
            } while ($this->isRunning() && microtime(true) < $timeoutMicro);

            if ($this->isRunning() && !$this->isSigchildEnabled()) {
                if (null !== $signal || defined('SIGKILL')) {
                    $this->signal($signal ?: SIGKILL);
                }
            }

            $this->updateStatus(false);
        }
        $this->status = self::STATUS_TERMINATED;

        return $this->exitcode;
    }

    /**
     * Adds a line to the STDOUT stream.
     *
     * @param string $line The line to append
     */
    public function addOutput($line)
    {
        $this->stdout .= $line;
    }

    /**
     * Adds a line to the STDERR stream.
     *
     * @param string $line The line to append
     */
    public function addErrorOutput($line)
    {
        $this->stderr .= $line;
    }

    /**
     * Gets the command line to be executed.
     *
     * @return string The command to execute
     */
    public function getCommandLine()
    {
        return $this->commandline;
    }

    /**
     * Sets the command line to be executed.
     *
     * @param string $commandline The command to execute
     *
     * @return self The current Process instance
     */
    public function setCommandLine($commandline)
    {
        $this->commandline = $commandline;

        return $this;
    }

    /**
     * Gets the process timeout.
     *
     * @return integer|null The timeout in seconds or null if it's disabled
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Sets the process timeout.
     *
     * To disable the timeout, set this value to null.
     *
     * @param float|null $timeout The timeout in seconds
     *
     * @return self The current Process instance
     *
     * @throws InvalidArgumentException if the timeout is negative
     */
    public function setTimeout($timeout)
    {
        if (null === $timeout) {
            $this->timeout = null;

            return $this;
        }

        $timeout = (float) $timeout;

        if ($timeout < 0) {
            throw new InvalidArgumentException('The timeout value must be a valid positive integer or float number.');
        }

        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Enables or disables the TTY mode.
     *
     * @param boolean $tty True to enabled and false to disable
     *
     * @return self The current Process instance
     */
    public function setTty($tty)
    {
        $this->tty = (Boolean) $tty;

        return $this;
    }

    /**
     * Checks if  the TTY mode is enabled.
     *
     * @return Boolean true if the TTY mode is enabled, false otherwise
     */
    public function isTty()
    {
        return $this->tty;
    }

    /**
     * Gets the working directory.
     *
     * @return string The current working directory
     */
    public function getWorkingDirectory()
    {
        // This is for BC only
        if (null === $this->cwd) {
            // getcwd() will return false if any one of the parent directories does not have
            // the readable or search mode set, even if the current directory does
            return getcwd() ?: null;
        }

        return $this->cwd;
    }

    /**
     * Sets the current working directory.
     *
     * @param string $cwd The new working directory
     *
     * @return self The current Process instance
     */
    public function setWorkingDirectory($cwd)
    {
        $this->cwd = $cwd;

        return $this;
    }

    /**
     * Gets the environment variables.
     *
     * @return array The current environment variables
     */
    public function getEnv()
    {
        return $this->env;
    }

    /**
     * Sets the environment variables.
     *
     * An environment variable value should be a string.
     * If it is an array, the variable is ignored.
     *
     * That happens in PHP when 'argv' is registered into
     * the $_ENV array for instance.
     *
     * @param array $env The new environment variables
     *
     * @return self The current Process instance
     */
    public function setEnv(array $env)
    {
        // Process can not handle env values that are arrays
        $env = array_filter($env, function ($value) { if (!is_array($value)) { return true; } });

        $this->env = array();
        foreach ($env as $key => $value) {
            $this->env[(binary) $key] = (binary) $value;
        }

        return $this;
    }

    /**
     * Gets the contents of STDIN.
     *
     * @return string The current contents
     */
    public function getStdin()
    {
        return $this->stdin;
    }

    /**
     * Sets the contents of STDIN.
     *
     * @param string $stdin The new contents
     *
     * @return self The current Process instance
     */
    public function setStdin($stdin)
    {
        $this->stdin = $stdin;

        return $this;
    }

    /**
     * Gets the options for proc_open.
     *
     * @return array The current options
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Sets the options for proc_open.
     *
     * @param array $options The new options
     *
     * @return self The current Process instance
     */
    public function setOptions(array $options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Gets whether or not Windows compatibility is enabled.
     *
     * This is true by default.
     *
     * @return Boolean
     */
    public function getEnhanceWindowsCompatibility()
    {
        return $this->enhanceWindowsCompatibility;
    }

    /**
     * Sets whether or not Windows compatibility is enabled.
     *
     * @param Boolean $enhance
     *
     * @return self The current Process instance
     */
    public function setEnhanceWindowsCompatibility($enhance)
    {
        $this->enhanceWindowsCompatibility = (Boolean) $enhance;

        return $this;
    }

    /**
     * Returns whether sigchild compatibility mode is activated or not.
     *
     * @return Boolean
     */
    public function getEnhanceSigchildCompatibility()
    {
        return $this->enhanceSigchildCompatibility;
    }

    /**
     * Activates sigchild compatibility mode.
     *
     * Sigchild compatibility mode is required to get the exit code and
     * determine the success of a process when PHP has been compiled with
     * the --enable-sigchild option
     *
     * @param Boolean $enhance
     *
     * @return self The current Process instance
     */
    public function setEnhanceSigchildCompatibility($enhance)
    {
        $this->enhanceSigchildCompatibility = (Boolean) $enhance;

        return $this;
    }

    /**
     * Performs a check between the timeout definition and the time the process started.
     *
     * In case you run a background process (with the start method), you should
     * trigger this method regularly to ensure the process timeout
     *
     * @throws RuntimeException In case the timeout was reached
     */
    public function checkTimeout()
    {
        if (0 < $this->timeout && $this->timeout < microtime(true) - $this->starttime) {
            $this->stop(0);

            throw new RuntimeException('The process timed-out.');
        }
    }

    /**
     * Creates the descriptors needed by the proc_open.
     *
     * @return array
     */
    private function getDescriptors()
    {
        //Fix for PHP bug #51800: reading from STDOUT pipe hangs forever on Windows if the output is too big.
        //Workaround for this problem is to use temporary files instead of pipes on Windows platform.
        //@see https://bugs.php.net/bug.php?id=51800
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->fileHandles = array(
                self::STDOUT => tmpfile(),
            );
            if (false === $this->fileHandles[self::STDOUT]) {
                throw new RuntimeException('A temporary file could not be opened to write the process output to, verify that your TEMP environment variable is writable');
            }
            $this->readBytes = array(
                self::STDOUT => 0,
            );

            return array(array('pipe', 'r'), $this->fileHandles[self::STDOUT], array('pipe', 'w'));
        }

        if ($this->tty) {
            $descriptors = array(
                array('file', '/dev/tty', 'r'),
                array('file', '/dev/tty', 'w'),
                array('file', '/dev/tty', 'w'),
            );
        } else {
           $descriptors = array(
                array('pipe', 'r'), // stdin
                array('pipe', 'w'), // stdout
                array('pipe', 'w'), // stderr
            );
        }

        if ($this->enhanceSigchildCompatibility && $this->isSigchildEnabled()) {
            // last exit code is output on the fourth pipe and caught to work around --enable-sigchild
            $descriptors = array_merge($descriptors, array(array('pipe', 'w')));

            $this->commandline = '('.$this->commandline.') 3>/dev/null; code=$?; echo $code >&3; exit $code';
        }

        return $descriptors;
    }

    /**
     * Builds up the callback used by wait().
     *
     * The callbacks adds all occurred output to the specific buffer and calls
     * the user callback (if present) with the received output.
     *
     * @param callback|null $callback The user defined PHP callback
     *
     * @return callback A PHP callable
     */
    protected function buildCallback($callback)
    {
        $that = $this;
        $out = self::OUT;
        $err = self::ERR;
        $callback = function ($type, $data) use ($that, $callback, $out, $err) {
            if ($out == $type) {
                $that->addOutput($data);
            } else {
                $that->addErrorOutput($data);
            }

            if (null !== $callback) {
                call_user_func($callback, $type, $data);
            }
        };

        return $callback;
    }

    /**
     * Updates the status of the process, reads pipes.
     *
     * @param Boolean $blocking Whether to use a clocking read call.
     */
    protected function updateStatus($blocking)
    {
        if (self::STATUS_STARTED !== $this->status) {
            return;
        }

        $this->readPipes($blocking);

        $this->processInformation = proc_get_status($this->process);
        $this->captureExitCode();
        if (!$this->processInformation['running']) {
            $this->close();
            $this->status = self::STATUS_TERMINATED;
        }
    }

    /**
     * Returns whether PHP has been compiled with the '--enable-sigchild' option or not.
     *
     * @return Boolean
     */
    protected function isSigchildEnabled()
    {
        if (null !== self::$sigchild) {
            return self::$sigchild;
        }

        ob_start();
        phpinfo(INFO_GENERAL);

        return self::$sigchild = false !== strpos(ob_get_clean(), '--enable-sigchild');
    }

    /**
     * Handles the windows file handles fallbacks.
     *
     * @param Boolean $closeEmptyHandles if true, handles that are empty will be assumed closed
     */
    private function processFileHandles($closeEmptyHandles = false)
    {
        $fh = $this->fileHandles;
        foreach ($fh as $type => $fileHandle) {
            fseek($fileHandle, $this->readBytes[$type]);
            $data = fread($fileHandle, 8192);
            if (strlen($data) > 0) {
                $this->readBytes[$type] += strlen($data);
                call_user_func($this->callback, $type == 1 ? self::OUT : self::ERR, $data);
            }
            if (false === $data || ($closeEmptyHandles && '' === $data && feof($fileHandle))) {
                fclose($fileHandle);
                unset($this->fileHandles[$type]);
            }
        }
    }

    /**
     * Returns true if a system call has been interrupted.
     *
     * @return Boolean
     */
    private function hasSystemCallBeenInterrupted()
    {
        $lastError = error_get_last();

        // stream_select returns false when the `select` system call is interrupted by an incoming signal
        return isset($lastError['message']) && false !== stripos($lastError['message'], 'interrupted system call');
    }

    /**
     * Reads pipes, executes callback.
     *
     * @param Boolean $blocking Whether to use blocking calls or not.
     */
    private function readPipes($blocking)
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD') && $this->fileHandles) {
            $this->processFileHandles(!$this->pipes);
        }

        if ($this->pipes) {
            $r = $this->pipes;
            $w = null;
            $e = null;

            // let's have a look if something changed in streams
            if (false === $n = @stream_select($r, $w, $e, 0, $blocking ? ceil(self::TIMEOUT_PRECISION * 1E6) : 0)) {
                // if a system call has been interrupted, forget about it, let's try again
                // otherwise, an error occured, let's reset pipes
                if (!$this->hasSystemCallBeenInterrupted()) {
                    $this->pipes = array();
                }

                return;
            }

            // nothing has changed
            if (0 === $n) {
                return;
            }

            $this->processReadPipes($r);
        }
    }

    /**
     * Writes data to pipes.
     *
     * @param Boolean $blocking Whether to use blocking calls or not.
     */
    private function writePipes()
    {
        if ($this->tty) {
            $this->status = self::STATUS_TERMINATED;

            return;
        }

        if (null === $this->stdin) {
            fclose($this->pipes[0]);
            unset($this->pipes[0]);

            return;
        }

        $writePipes = array($this->pipes[0]);
        unset($this->pipes[0]);
        $stdinLen = strlen($this->stdin);
        $stdinOffset = 0;

        while ($writePipes) {
            if (defined('PHP_WINDOWS_VERSION_BUILD')) {
                $this->processFileHandles();
            }

            $r = $this->pipes;
            $w = $writePipes;
            $e = null;

            if (false === $n = @stream_select($r, $w, $e, 0, $blocking ? ceil(static::TIMEOUT_PRECISION * 1E6) : 0)) {
                // if a system call has been interrupted, forget about it, let's try again
                if ($this->hasSystemCallBeenInterrupted()) {
                    continue;
                }
                break;
            }

            // nothing has changed, let's wait until the process is ready
            if (0 === $n) {
                continue;
            }

            if ($w) {
                $written = fwrite($writePipes[0], (binary) substr($this->stdin, $stdinOffset), 8192);
                if (false !== $written) {
                    $stdinOffset += $written;
                }
                if ($stdinOffset >= $stdinLen) {
                    fclose($writePipes[0]);
                    $writePipes = null;
                }
            }

            $this->processReadPipes($r);
        }
    }

    /**
     * Processes read pipes, executes callback on it.
     *
     * @param array $pipes
     */
    private function processReadPipes(array $pipes)
    {
        foreach ($pipes as $pipe) {
            $type = array_search($pipe, $this->pipes);
            $data = fread($pipe, 8192);

            if (strlen($data) > 0) {
                // last exit code is output and caught to work around --enable-sigchild
                if (3 == $type) {
                    $this->fallbackExitcode = (int) $data;
                } else {
                    call_user_func($this->callback, $type == 1 ? self::OUT : self::ERR, $data);
                }
            }
            if (false === $data || feof($pipe)) {
                fclose($pipe);
                unset($this->pipes[$type]);
            }
        }
    }

    /**
     * Captures the exitcode if mentioned in the process informations.
     */
    private function captureExitCode()
    {
        if (isset($this->processInformation['exitcode']) && -1 != $this->processInformation['exitcode']) {
            $this->exitcode = $this->processInformation['exitcode'];
        }
    }


    /**
     * Closes process resource, closes file handles, sets the exitcode.
     *
     * @return Integer The exitcode
     */
    private function close()
    {
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }

        $this->pipes = null;
        $exitcode = -1;

        if (is_resource($this->process)) {
            $exitcode = proc_close($this->process);
        }

        $this->exitcode = $this->exitcode !== null ? $this->exitcode : -1;
        $this->exitcode = -1 != $exitcode ? $exitcode : $this->exitcode;

        if (-1 == $this->exitcode && null !== $this->fallbackExitcode) {
            $this->exitcode = $this->fallbackExitcode;
        } elseif (-1 === $this->exitcode && $this->processInformation['signaled'] && 0 < $this->processInformation['termsig']) {
            // if process has been signaled, no exitcode but a valid termsig, apply unix convention
            $this->exitcode = 128 + $this->processInformation['termsig'];
        }

        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            foreach ($this->fileHandles as $fileHandle) {
                fclose($fileHandle);
            }
            $this->fileHandles = array();
        }

        return $this->exitcode;
    }

    /**
     * Resets data related to the latest run of the process.
     */
    private function resetProcessData()
    {
        $this->starttime = null;
        $this->callback = null;
        $this->exitcode = null;
        $this->fallbackExitcode = null;
        $this->processInformation = null;
        $this->stdout = null;
        $this->stderr = null;
        $this->pipes = null;
        $this->process = null;
        $this->status = self::STATUS_READY;
        $this->fileHandles = null;
        $this->readBytes = null;
        $this->incrementalOutputOffset = 0;
        $this->incrementalErrorOutputOffset = 0;
    }
}
