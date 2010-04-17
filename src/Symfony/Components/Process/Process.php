<?php

namespace Symfony\Components\Process;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Process is a thin wrapper around proc_* functions to ease
 * the forking processes from PHP.
 *
 * @package    Symfony
 * @subpackage Components_Process
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Process
{
  protected $commandline;
  protected $cwd;
  protected $env;
  protected $stdin;
  protected $timeout;
  protected $options;
  protected $exitcode;
  protected $status;

  /**
   * Constructor.
   *
   * @param string  $commandline The command line to run
   * @param string  $cwd         The working directory
   * @param array   $env         The environment variables
   * @param string  $stdin       The STDIN content
   * @param integer $timeout     The timeout in seconds
   * @param array   $options     An array of options for proc_open
   */
  public function __construct($commandline, $cwd, array $env = array(), $stdin = null, $timeout = 60, array $options = array())
  {
    if (!function_exists('proc_open'))
    {
      throw new \RuntimeException('The Process class relies on proc_open, which is not available on your PHP installation.');
    }

    $this->commandline = $commandline;
    $this->cwd = null === $cwd ? getcwd() : $cwd;
    $this->env = array();
    foreach ($env as $key => $value)
    {
      $this->env[(binary) $key] = (binary) $value;
    }
    $this->stdin = $stdin;
    $this->timeout = $timeout;
    $this->options = array_merge($options, array('suppress_errors' => true, 'binary_pipes' => true));
  }

  /**
   * Forks and run the process.
   *
   * @param Closure|string|array $callback A PHP callback to run whenever there is some
   *                                       output available on STDOUT or STDERR
   *
   * @return integer The exit status code
   */
  public function run($callback)
  {
    $descriptors = array(array('pipe', 'r'), array('pipe', 'w'), array('pipe', 'w'));

    $proccess = proc_open($this->commandline, $descriptors, $pipes, $this->cwd, $this->env, $this->options);

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    if (!is_resource($proccess))
    {
      throw new \RuntimeException('Unable to launch a new process.');
    }

    if (!is_null($this->stdin))
    {
      fwrite($pipes[0], (binary) $this->stdin);
    }
    fclose($pipes[0]);

    while (true)
    {
      $r = $pipes;
      $w = null;
      $e = null;

      $n = @stream_select($r, $w, $e, $this->timeout);

      if ($n === false)
      {
        break;
      }
      elseif ($n === 0)
      {
        proc_terminate($proccess);

        throw new \RuntimeException('The process timed out.');
      }
      elseif ($n > 0)
      {
        $called = false;

        while (true)
        {
          $c = false;
          if ($line = (binary) fgets($pipes[1], 1024))
          {
            $called = $c = true;
            call_user_func($callback, 'out', $line);
          }

          if ($line = fgets($pipes[2], 1024))
          {
            $called = $c = true;
            call_user_func($callback, 'err', $line);
          }

          if (!$c)
          {
            break;
          }
        }

        if (!$called)
        {
          break;
        }
      }
    }

    $this->status = proc_get_status($proccess);

    proc_close($proccess);

    if ($this->status['signaled'])
    {
      throw new \RuntimeException(sprintf('The process stopped because of a "%s" signal.', $this->status['stopsig']));
    }

    return $this->exitcode = $this->status['exitcode'];
  }

  /**
   * Returns the exit code returned by the process.
   *
   * @return integer The exit status code
   */
  public function getExitCode()
  {
    return $this->exitcode;
  }

  /**
   * Returns true if the child process has been terminated by an uncaught signal.
   *
   * It always returns false on Windows.
   *
   * @return Boolean
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
   */
  public function getStopSignal()
  {
    return $this->status['stopsig'];
  }
}
