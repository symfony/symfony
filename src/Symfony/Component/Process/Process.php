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
    private $commandline;
    private $cwd;
    private $env;
    private $stdin;
    private $timeout;
    private $options;
    private $exitcode;
    private $status;
    private $stdout;
    private $stderr;

    /**
     * Constructor.
     *
     * @param string  $commandline The command line to run
     * @param string  $cwd         The working directory
     * @param array   $env         The environment variables
     * @param string  $stdin       The STDIN content
     * @param integer $timeout     The timeout in seconds
     * @param array   $options     An array of options for proc_open
     *
     * @throws \RuntimeException When proc_open is not installed
     *
     * @api
     */
    public function __construct($commandline, $cwd = null, array $env = array(), $stdin = null, $timeout = 60, array $options = array())
    {
        if (!function_exists('proc_open')) {
            throw new \RuntimeException('The Process class relies on proc_open, which is not available on your PHP installation.');
        }

        $this->commandline = $commandline;
        $this->cwd = null === $cwd ? getcwd() : $cwd;
        $this->env = array();
        foreach ($env as $key => $value) {
            $this->env[(binary) $key] = (binary) $value;
        }
        $this->stdin = $stdin;
        $this->timeout = $timeout;
        $this->options = array_merge(array('suppress_errors' => true, 'binary_pipes' => true, 'bypass_shell' => true), $options);
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
     * @param Closure|string|array $callback A PHP callback to run whenever there is some
     *                                       output available on STDOUT or STDERR
     *
     * @return integer The exit status code
     *
     * @throws \RuntimeException When process can't be launch or is stopped
     *
     * @api
     */
    public function run($callback = null)
    {
        $this->stdout = '';
        $this->stderr = '';
        $that = $this;
        $callback = function ($type, $line) use ($that, $callback)
        {
            if ('out' == $type) {
                $that->addOutput($line);
            } else {
                $that->addErrorOutput($line);
            }

            if (null !== $callback) {
                call_user_func($callback, $type, $line);
            }
        };

        $descriptors = array(array('pipe', 'r'), array('pipe', 'w'), array('pipe', 'w'));

        $process = proc_open($this->commandline, $descriptors, $pipes, $this->cwd, $this->env, $this->options);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to launch a new process.');
        }

        if (null !== $this->stdin) {
            fwrite($pipes[0], (binary) $this->stdin);
        }
        fclose($pipes[0]);

        while (true) {
            $r = $pipes;
            $w = null;
            $e = null;

            $n = @stream_select($r, $w, $e, $this->timeout);

            if (false === $n) {
                break;
            } elseif ($n === 0) {
                proc_terminate($process);

                throw new \RuntimeException('The process timed out.');
            } elseif ($n > 0) {
                $called = false;

                while (true) {
                    $c = false;
                    if ($line = (binary) fgets($pipes[1], 1024)) {
                        $called = $c = true;
                        call_user_func($callback, 'out', $line);
                    }

                    if ($line = fgets($pipes[2], 1024)) {
                        $called = $c = true;
                        call_user_func($callback, 'err', $line);
                    }

                    if (!$c) {
                        break;
                    }
                }

                if (!$called) {
                    break;
                }
            }
        }

        $this->status = proc_get_status($process);

        proc_close($process);

        if ($this->status['signaled']) {
            throw new \RuntimeException(sprintf('The process stopped because of a "%s" signal.', $this->status['stopsig']));
        }

        return $this->exitcode = $this->status['exitcode'];
    }

    /**
     * Returns the output of the process (STDOUT).
     *
     * This only returns the output if you have not supplied a callback
     * to the run() method.
     *
     * @return string The process output
     *
     * @api
     */
    public function getOutput()
    {
        return $this->stdout;
    }

    /**
     * Returns the error output of the process (STDERR).
     *
     * This only returns the error output if you have not supplied a callback
     * to the run() method.
     *
     * @return string The process error output
     *
     * @api
     */
    public function getErrorOutput()
    {
        return $this->stderr;
    }

    /**
     * Returns the exit code returned by the process.
     *
     * @return integer The exit status code
     *
     * @api
     */
    public function getExitCode()
    {
        return $this->exitcode;
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
        return 0 == $this->exitcode;
    }

    /**
     * Returns true if the child process has been terminated by an uncaught signal.
     *
     * It always returns false on Windows.
     *
     * @return Boolean
     *
     * @api
     */
    public function hasBeenSignaled()
    {
        return $this->status['signaled'];
    }

    /**
     * Returns the number of the signal that caused the child process to terminate its execution.
     *
     * It is only meaningful if hasBeenSignaled() returns true.
     *
     * @return integer
     *
     * @api
     */
    public function getTermSignal()
    {
        return $this->status['termsig'];
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
        return $this->status['stopped'];
    }

    /**
     * Returns the number of the signal that caused the child process to stop its execution
     *
     * It is only meaningful if hasBeenStopped() returns true.
     *
     * @return integer
     *
     * @api
     */
    public function getStopSignal()
    {
        return $this->status['stopsig'];
    }

    public function addOutput($line)
    {
        $this->stdout .= $line;
    }

    public function addErrorOutput($line)
    {
        $this->stderr .= $line;
    }

    public function getCommandLine()
    {
        return $this->commandline;
    }

    public function setCommandLine($commandline)
    {
        $this->commandline = $commandline;
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function getWorkingDirectory()
    {
        return $this->cwd;
    }

    public function setWorkingDirectory($cwd)
    {
        $this->cwd = $cwd;
    }

    public function getEnv()
    {
        return $this->env;
    }

    public function setEnv(array $env)
    {
        $this->env = $env;
    }

    public function getStdin()
    {
        return $this->stdin;
    }

    public function setStdin($stdin)
    {
        $this->stdin = $stdin;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setOptions(array $options)
    {
        $this->options = $options;
    }
}
