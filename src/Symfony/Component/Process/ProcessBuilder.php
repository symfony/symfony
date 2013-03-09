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

    public function __construct(array $arguments = array())
    {
        $this->arguments = $arguments;

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
     * Adds an unescaped argument to the command string.
     *
     * @param string $argument A command argument
     *
     * @return ProcessBuilder
     */
    public function add($argument)
    {
        $this->arguments[] = $argument;

        return $this;
    }

    /**
     * @param array $arguments
     *
     * @return ProcessBuilder
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }

    public function setWorkingDirectory($cwd)
    {
        $this->cwd = $cwd;

        return $this;
    }

    public function inheritEnvironmentVariables($inheritEnv = true)
    {
        $this->inheritEnv = $inheritEnv;

        return $this;
    }

    public function setEnv($name, $value)
    {
        $this->env[$name] = $value;

        return $this;
    }

    public function setInput($stdin)
    {
        $this->stdin = $stdin;

        return $this;
    }

    /**
     * Sets the process timeout.
     *
     * To disable the timeout, set this value to null.
     *
     * @param integer|null
     *
     * @return ProcessBuilder
     *
     * @throws InvalidArgumentException
     */
    public function setTimeout($timeout)
    {
        if (null === $timeout) {
            $this->timeout = null;

            return $this;
        }

        $timeout = (integer) $timeout;

        if ($timeout < 0) {
            throw new InvalidArgumentException('The timeout value must be a valid positive integer.');
        }

        $this->timeout = $timeout;

        return $this;
    }

    public function setOption($name, $value)
    {
        $this->options[$name] = $value;

        return $this;
    }

    public function getProcess()
    {
        if (!count($this->arguments)) {
            throw new LogicException('You must add() command arguments before calling getProcess().');
        }

        $options = $this->options;

        $script = implode(' ', array_map('escapeshellarg', $this->arguments));

        if ($this->inheritEnv) {
            $env = $this->env ? $this->env + $_ENV : null;
        } else {
            $env = $this->env;
        }

        return new Process($script, $this->cwd, $env, $this->stdin, $this->timeout, $options);
    }
}
