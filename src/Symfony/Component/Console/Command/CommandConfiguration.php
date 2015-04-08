<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Configuration of a command.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 *
 * @api
 */
class CommandConfiguration
{
    private $enabled = true;
    private $name;
    private $processTitle;
    private $aliases = array();
    private $definition;
    private $help;
    private $description;
    private $synopsis;
    /**
     * @var Command|callable|null
     */
    private $command;

    /**
     * @param Command|callable|null $command
     */
    public function __construct($command = null)
    {
        $this->definition = new InputDefinition();
        if ($command) {
            $this->setCommand($command);
        }
    }

    /**
     * Set the Command instance or a callable returning the Command instance.
     *
     * Provide a callable if you want the Command to be instantiated lazily.
     *
     * @param Command|callable $command
     *
     * @api
     */
    public function setCommand($command)
    {
        if ((!$command instanceof Command) && !is_callable($command)) {
            throw new \LogicException('The command must be a Command instance or a callable returning a Command instance');
        }

        $this->command = $command;
    }

    /**
     * @return Command
     *
     * @api
     */
    public function getCommand()
    {
        if ($this->command instanceof Command) {
            return $this->command;
        }

        if (null === $this->command) {
            throw new \LogicException('No command was set');
        }

        $this->setCommand(call_user_func($this->command, $this));

        return $this->command;
    }

    /**
     * Set whether the command is enabled or not in the current environment.
     *
     * Set this to false if the command can not run properly under the current conditions.
     *
     * @param $enabled
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
    }

    /**
     * Whether the command is enabled or not in the current environment.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Gets the InputDefinition attached to this Command.
     *
     * @return InputDefinition An InputDefinition instance
     *
     * @api
     */
    public function getDefinition()
    {
        return $this->definition;
    }

    /**
     * Sets an array of argument and option instances.
     *
     * @param array|InputDefinition $definition An array of argument and option instances or a definition instance
     *
     * @return CommandConfiguration The current instance
     *
     * @api
     */
    public function setDefinition($definition)
    {
        if ($definition instanceof InputDefinition) {
            $this->definition = $definition;
        } else {
            $this->definition->setDefinition($definition);
        }

        return $this;
    }

    /**
     * Adds an argument.
     *
     * @param string $name        The argument name
     * @param int    $mode        The argument mode: InputArgument::REQUIRED or InputArgument::OPTIONAL
     * @param string $description A description text
     * @param mixed  $default     The default value (for InputArgument::OPTIONAL mode only)
     *
     * @return CommandConfiguration The current instance
     *
     * @api
     */
    public function addArgument($name, $mode = null, $description = '', $default = null)
    {
        $this->definition->addArgument(new InputArgument($name, $mode, $description, $default));

        return $this;
    }

    /**
     * Adds an option.
     *
     * @param string $name        The option name
     * @param string $shortcut    The shortcut (can be null)
     * @param int    $mode        The option mode: One of the InputOption::VALUE_* constants
     * @param string $description A description text
     * @param mixed  $default     The default value (must be null for InputOption::VALUE_REQUIRED or InputOption::VALUE_NONE)
     *
     * @return CommandConfiguration The current instance
     *
     * @api
     */
    public function addOption($name, $shortcut = null, $mode = null, $description = '', $default = null)
    {
        $this->definition->addOption(new InputOption($name, $shortcut, $mode, $description, $default));

        return $this;
    }

    /**
     * Sets the name of the command.
     *
     * This method can set both the namespace and the name if
     * you separate them by a colon (:)
     *
     *     $command->setName('foo:bar');
     *
     * @param string $name The command name
     *
     * @return CommandConfiguration The current instance
     *
     * @throws \InvalidArgumentException When the name is invalid
     *
     * @api
     */
    public function setName($name)
    {
        $this->validateName($name);

        $this->name = $name;

        return $this;
    }

    /**
     * Sets the process title of the command.
     *
     * This feature should be used only when creating a long process command,
     * like a daemon.
     *
     * PHP 5.5+ or the proctitle PECL library is required
     *
     * @param string $title The process title
     *
     * @return CommandConfiguration The current instance
     */
    public function setProcessTitle($title)
    {
        $this->processTitle = $title;

        return $this;
    }

    /**
     * @return string
     */
    public function getProcessTitle()
    {
        return $this->processTitle;
    }

    /**
     * Returns the command name.
     *
     * @return string The command name
     *
     * @api
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the description for the command.
     *
     * @param string $description The description for the command
     *
     * @return CommandConfiguration The current instance
     *
     * @api
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Returns the description for the command.
     *
     * @return string The description for the command
     *
     * @api
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Sets the help for the command.
     *
     * @param string $help The help for the command
     *
     * @return CommandConfiguration The current instance
     *
     * @api
     */
    public function setHelp($help)
    {
        $this->help = $help;

        return $this;
    }

    /**
     * Returns the help for the command.
     *
     * @return string The help for the command
     *
     * @api
     */
    public function getHelp()
    {
        return $this->help;
    }

    /**
     * Returns the processed help for the command replacing the %command.name% and
     * %command.full_name% patterns with the real values dynamically.
     *
     * @return string The processed help for the command
     */
    public function getProcessedHelp()
    {
        $name = $this->name;

        $placeholders = array(
            '%command.name%',
            '%command.full_name%',
        );
        $replacements = array(
            $name,
            $_SERVER['PHP_SELF'].' '.$name,
        );

        return str_replace($placeholders, $replacements, $this->getHelp());
    }

    /**
     * Sets the aliases for the command.
     *
     * @param string[] $aliases An array of aliases for the command
     *
     * @return CommandConfiguration The current instance
     *
     * @throws \InvalidArgumentException When an alias is invalid
     *
     * @api
     */
    public function setAliases($aliases)
    {
        if (!is_array($aliases) && !$aliases instanceof \Traversable) {
            throw new \InvalidArgumentException('$aliases must be an array or an instance of \Traversable');
        }

        foreach ($aliases as $alias) {
            $this->validateName($alias);
        }

        $this->aliases = $aliases;

        return $this;
    }

    /**
     * Returns the aliases for the command.
     *
     * @return array An array of aliases for the command
     *
     * @api
     */
    public function getAliases()
    {
        return $this->aliases;
    }

    /**
     * Returns the synopsis for the command.
     *
     * @return string The synopsis
     */
    public function getSynopsis()
    {
        if (null === $this->synopsis) {
            $this->synopsis = trim(sprintf('%s %s', $this->name, $this->definition->getSynopsis()));
        }

        return $this->synopsis;
    }

    /**
     * Validates a command name.
     *
     * It must be non-empty and parts can optionally be separated by ":".
     *
     * @param string $name
     *
     * @throws \InvalidArgumentException When the name is invalid
     */
    private function validateName($name)
    {
        if (!preg_match('/^[^\:]++(\:[^\:]++)*$/', $name)) {
            throw new \InvalidArgumentException(sprintf('Command name "%s" is invalid.', $name));
        }
    }
}
