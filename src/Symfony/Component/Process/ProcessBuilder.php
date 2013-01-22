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

/**
 * Process builder.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class ProcessBuilder
{
    private $arguments;
    private $cwd;
    private $env;
    private $stdin;
    private $timeout;
    private $options;
    private $inheritEnv;

    /**
     * @param string[] $arguments The command line arguments. They will always get escaped.
     *
     * @see add
     */
    public function __construct(array $arguments = array())
    {
        $this->setArguments($arguments);
        $this->timeout = 60;
        $this->options = array();
        $this->env = array();
        $this->inheritEnv = true;
    }

    public static function create(array $arguments = array())
    {
        return new static($arguments);
    }

    /**
     * Adds an argument to the command string.
     *
     * The argument will get escaped unless you instruct to do so by setting the
     * $escape flag to false. Setting the $escape flag to false is not recommended
     * unless you understand the implications.
     *
     * @param string  $argument A command argument
     * @param Boolean $escape   Whether the argument should be escaped (default)
     *
     * @return ProcessBuilder
     *
     * @throws InvalidArgumentException When the argument is not a string
     */
    public function add($argument, $escape = true)
    {
        if (!is_string($argument)) {
            throw new InvalidArgumentException('The argument must be a string.');
        }

        $this->arguments[] = array($argument, $escape);

        return $this;
    }

    /**
     * @param string[] $arguments
     *
     * @return ProcessBuilder
     *
     * @throws InvalidArgumentException When any argument is not a string
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = array_map(function($arg) {
                if (!is_string($arg)) {
                    throw new InvalidArgumentException('Arguments must be strings.');
                }

                return array($arg, true);
            },
            $arguments
        );

        return $this;
    }

    /**
     * Sets the working directory for the process.
     *
     * @param string $cwd The working directory
     *
     * @return ProcessBuilder
     */
    public function setWorkingDirectory($cwd)
    {
        $this->cwd = $cwd;

        return $this;
    }

    /**
     * Sets whether to inherit from the environment variables.
     *
     * @param Boolean $inheritEnv
     *
     * @return ProcessBuilder
     */
    public function inheritEnvironmentVariables($inheritEnv = true)
    {
        $this->inheritEnv = (Boolean) $inheritEnv;

        return $this;
    }

    /**
     * Sets the value of an environment variable.
     *
     * @param string $name
     * @param string $value
     *
     * @return ProcessBuilder
     */
    public function setEnv($name, $value)
    {
        $this->env[$name] = $value;

        return $this;
    }

    /**
     * Sets the content for STDIN.
     *
     * @param string $stdin The content for STDIN
     *
     * @return ProcessBuilder
     */
    public function setInput($stdin)
    {
        $this->stdin = $stdin;

        return $this;
    }

    /**
     * Sets the process timeout.
     *
     * @param integer The timeout in seconds, 0 to disable.
     *
     * @return ProcessBuilder
     *
     * @throws InvalidArgumentException When the timeout value is invalid (<0)
     */
    public function setTimeout($timeout)
    {
        $this->timeout = (integer) $timeout;

        if ($this->timeout < 0) {
            throw new InvalidArgumentException('The timeout value must be a valid positive integer.');
        }

        return $this;
    }

    /**
     * Sets an option for proc_open
     *
     * @param string $name
     * @param string $value
     *
     * @return ProcessBuilder
     */
    public function setOption($name, $value)
    {
        $this->options[$name] = $value;

        return $this;
    }

    /**
     * @return Process
     *
     * @throws Exception\LogicException When no arguments have been specified
     */
    public function getProcess()
    {
        if (!count($this->arguments)) {
            throw new LogicException('You must add() command arguments before calling getProcess().');
        }

        $options = $this->options;

        $script = implode(' ', array_map(function($arg) { return $arg[1] ? escapeshellarg($arg[0]) : $arg[0]; }, $this->arguments));

        if ($this->inheritEnv) {
            $env = $this->env ? $this->env + $_ENV : null;
        } else {
            $env = $this->env;
        }

        return new Process($script, $this->cwd, $env, $this->stdin, $this->timeout, $options);
    }
}
