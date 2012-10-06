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
    public function __construct($commandline, $cwd = null, array $env = null, $stdin = null, $timeout = 60, array $options = array())
    {
        if (!function_exists('proc_open')) {
            throw new \RuntimeException('The Process class relies on proc_open, which is not available on your PHP installation.');
        }

        $this->commandline = $commandline;
        $this->cwd = null === $cwd ? getcwd() : $cwd;
        if (null !== $env) {
            $this->env = array();
            foreach ($env as $key => $value) {
                $this->env[(binary) $key] = (binary) $value;
            }
        } else {
            $this->env = null;
        }
        $this->stdin = $stdin;
        $this->timeout = $timeout;
        $this->options = array_merge(array('suppress_errors' => true, 'binary_pipes' => true, 'bypass_shell' => false), $options);
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
        $callback = function ($type, $data) use ($that, $callback) {
            if ('out' == $type) {
                $that->addOutput($data);
            } else {
                $that->addErrorOutput($data);
            }

            if (null !== $callback) {
                call_user_func($callback, $type, $data);
            }
        };

        $descriptors = array(array('pipe', 'r'), array('pipe', 'w'), array('pipe', 'w'));

        $process = proc_open($this->commandline, $descriptors, $pipes, $this->cwd, $this->env, $this->options);

        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to launch a new process.');
        }

        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        if (null === $this->stdin) {
            fclose($pipes[0]);
            $writePipes = null;
        } else {
            $writePipes = array($pipes[0]);
            $stdinLen = strlen($this->stdin);
            $stdinOffset = 0;
        }
        unset($pipes[0]);

        while ($pipes || $writePipes) {
            $r = $pipes;
            $w = $writePipes;
            $e = null;

            $n = @stream_select($r, $w, $e, $this->timeout);

            if (false === $n) {
                break;
            } elseif ($n === 0) {
                proc_terminate($process);

                throw new \RuntimeException('The process timed out.');
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

            foreach ($r as $pipe) {
                $type = array_search($pipe, $pipes);
                $data = fread($pipe, 8192);
                if (strlen($data) > 0) {
                    call_user_func($callback, $type == 1 ? 'out' : 'err', $data);
                }
                if (false === $data || feof($pipe)) {
                    fclose($pipe);
                    unset($pipes[$type]);
                }
            }
        }

        $this->status = proc_get_status($process);

        $time = 0;
        while (1 == $this->status['running'] && $time < 1000000) {
            $time += 1000;
            usleep(1000);
            $this->status = proc_get_status($process);
        }

        $exitcode = proc_close($process);

        if ($this->status['signaled']) {
            throw new \RuntimeException(sprintf('The process stopped because of a "%s" signal.', $this->status['stopsig']));
        }

        return $this->exitcode = $this->status['running'] ? $exitcode : $this->status['exitcode'];
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
        return $this->status['stopsig'];
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
     */
    public function setCommandLine($commandline)
    {
        $this->commandline = $commandline;
    }

    /**
     * Gets the process timeout.
     *
     * @return integer The timeout in seconds
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Sets the process timeout.
     *
     * @param integer|null $timeout The timeout in seconds
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * Gets the working directory.
     *
     * @return string The current working directory
     */
    public function getWorkingDirectory()
    {
        return $this->cwd;
    }

    /**
     * Sets the current working directory.
     *
     * @param string $cwd The new working directory
     */
    public function setWorkingDirectory($cwd)
    {
        $this->cwd = $cwd;
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
     * @param array $env The new environment variables
     */
    public function setEnv(array $env)
    {
        $this->env = $env;
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
     */
    public function setStdin($stdin)
    {
        $this->stdin = $stdin;
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
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }
}
