<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Input;

/**
 * Input is the base class for all concrete Input classes.
 *
 * Three concrete classes are provided by default:
 *
 *  * `ArgvInput`: The input comes from the CLI arguments (argv)
 *  * `StringInput`: The input is provided as a string
 *  * `ArrayInput`: The input is provided as an array
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
abstract class Input implements InputInterface
{
    protected $definition;
    protected $options;
    protected $arguments;
    protected $interactive = true;

    /**
     * Constructor.
     *
     * @param InputDefinition $definition A InputDefinition instance
     *
     * @since v2.0.0
     */
    public function __construct(InputDefinition $definition = null)
    {
        if (null === $definition) {
            $this->arguments = array();
            $this->options = array();
            $this->definition = new InputDefinition();
        } else {
            $this->bind($definition);
            $this->validate();
        }
    }

    /**
     * Binds the current Input instance with the given arguments and options.
     *
     * @param InputDefinition $definition A InputDefinition instance
     *
     * @since v2.0.0
     */
    public function bind(InputDefinition $definition)
    {
        $this->arguments = array();
        $this->options = array();
        $this->definition = $definition;

        $this->parse();
    }

    /**
     * Processes command line arguments.
     *
     * @since v2.0.0
     */
    abstract protected function parse();

    /**
     * Validates the input.
     *
     * @throws \RuntimeException When not enough arguments are given
     *
     * @since v2.0.0
     */
    public function validate()
    {
        if (count($this->arguments) < $this->definition->getArgumentRequiredCount()) {
            throw new \RuntimeException('Not enough arguments.');
        }
    }

    /**
     * Checks if the input is interactive.
     *
     * @return Boolean Returns true if the input is interactive
     *
     * @since v2.0.0
     */
    public function isInteractive()
    {
        return $this->interactive;
    }

    /**
     * Sets the input interactivity.
     *
     * @param Boolean $interactive If the input should be interactive
     *
     * @since v2.0.0
     */
    public function setInteractive($interactive)
    {
        $this->interactive = (Boolean) $interactive;
    }

    /**
     * Returns the argument values.
     *
     * @return array An array of argument values
     *
     * @since v2.0.0
     */
    public function getArguments()
    {
        return array_merge($this->definition->getArgumentDefaults(), $this->arguments);
    }

    /**
     * Returns the argument value for a given argument name.
     *
     * @param string $name The argument name
     *
     * @return mixed The argument value
     *
     * @throws \InvalidArgumentException When argument given doesn't exist
     *
     * @since v2.0.0
     */
    public function getArgument($name)
    {
        if (!$this->definition->hasArgument($name)) {
            throw new \InvalidArgumentException(sprintf('The "%s" argument does not exist.', $name));
        }

        return isset($this->arguments[$name]) ? $this->arguments[$name] : $this->definition->getArgument($name)->getDefault();
    }

    /**
     * Sets an argument value by name.
     *
     * @param string $name  The argument name
     * @param string $value The argument value
     *
     * @throws \InvalidArgumentException When argument given doesn't exist
     *
     * @since v2.0.0
     */
    public function setArgument($name, $value)
    {
        if (!$this->definition->hasArgument($name)) {
            throw new \InvalidArgumentException(sprintf('The "%s" argument does not exist.', $name));
        }

        $this->arguments[$name] = $value;
    }

    /**
     * Returns true if an InputArgument object exists by name or position.
     *
     * @param string|integer $name The InputArgument name or position
     *
     * @return Boolean true if the InputArgument object exists, false otherwise
     *
     * @since v2.0.0
     */
    public function hasArgument($name)
    {
        return $this->definition->hasArgument($name);
    }

    /**
     * Returns the options values.
     *
     * @return array An array of option values
     *
     * @since v2.0.0
     */
    public function getOptions()
    {
        return array_merge($this->definition->getOptionDefaults(), $this->options);
    }

    /**
     * Returns the option value for a given option name.
     *
     * @param string $name The option name
     *
     * @return mixed The option value
     *
     * @throws \InvalidArgumentException When option given doesn't exist
     *
     * @since v2.0.0
     */
    public function getOption($name)
    {
        if (!$this->definition->hasOption($name)) {
            throw new \InvalidArgumentException(sprintf('The "%s" option does not exist.', $name));
        }

        return isset($this->options[$name]) ? $this->options[$name] : $this->definition->getOption($name)->getDefault();
    }

    /**
     * Sets an option value by name.
     *
     * @param string $name  The option name
     * @param string $value The option value
     *
     * @throws \InvalidArgumentException When option given doesn't exist
     *
     * @since v2.0.0
     */
    public function setOption($name, $value)
    {
        if (!$this->definition->hasOption($name)) {
            throw new \InvalidArgumentException(sprintf('The "%s" option does not exist.', $name));
        }

        $this->options[$name] = $value;
    }

    /**
     * Returns true if an InputOption object exists by name.
     *
     * @param string $name The InputOption name
     *
     * @return Boolean true if the InputOption object exists, false otherwise
     *
     * @since v2.0.0
     */
    public function hasOption($name)
    {
        return $this->definition->hasOption($name);
    }

    /**
     * Escapes a token through escapeshellarg if it contains unsafe chars
     *
     * @param string $token
     *
     * @return string
     *
     * @since v2.3.0
     */
    public function escapeToken($token)
    {
        return preg_match('{^[\w-]+$}', $token) ? $token : escapeshellarg($token);
    }
}
