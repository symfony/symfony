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
        $this->inheritEnv = false;
    }

    /**
     * Adds an unescaped argument to the command string.
     *
     * @param string $argument A command argument
     */
    public function add($argument)
    {
        $this->arguments[] = $argument;

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
        if (null === $this->env) {
            $this->env = array();
        }

        $this->env[$name] = $value;

        return $this;
    }

    public function setInput($stdin)
    {
        $this->stdin = $stdin;

        return $this;
    }

    public function setTimeout($timeout)
    {
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
            throw new \LogicException('You must add() command arguments before calling getProcess().');
        }

        $options = $this->options;

        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            $options['bypass_shell'] = true;

            $arguments = $this->arguments;
            $command = array_shift($arguments);

            $script = '"'.$command.'"';
            if ($arguments) {
                $script .= ' '.implode(' ', array_map('escapeshellarg', $arguments));
            }

            $script = 'cmd /V:ON /E:ON /C "'.$script.'"';
        } else {
            $script = implode(' ', array_map('escapeshellarg', $this->arguments));
        }

        $env = $this->inheritEnv && $_ENV ? ($this->env ?: array()) + $_ENV : $this->env;

        return new Process($script, $this->cwd, $env, $this->stdin, $this->timeout, $options);
    }
}
