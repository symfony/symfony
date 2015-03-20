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

/**
 * Configuration of a command.
 *
 * @author Nikita Konstantinov
 *
 * @api
 */
final class CommandConfiguration
{
    private $name;
    private $aliases = array();
    private $definition;
    private $enabled;
    private $processTitle;
    private $help;
    private $description;
    private $synopsis;

    /**
     * @param string $name
     * @return CommandConfiguration
     */
    public static function create($name)
    {
        return new self($name);
    }

    /**
     * @param string $name
     * @param InputDefinition $definition
     * @param string[] $aliases
     * @param bool $enabled
     * @param string $processTitle
     * @param string $help
     * @param string $description
     * @param string $synopsis
     */
    public function __construct(
        $name = null,
        InputDefinition $definition = null,
        array $aliases = array(),
        $enabled = true,
        $processTitle = null,
        $help = null,
        $description = null,
        $synopsis = null
    ) {
        if ($name !== null) {
            $this->validateName($name);
        }

        foreach ($aliases as $alias) {
            $this->validateName($alias);
        }

        $this->name = $name;
        $this->aliases = $aliases;
        $this->definition = $definition ?: new InputDefinition();
        $this->enabled = $enabled;
        $this->processTitle = $processTitle;
        $this->help = $help;
        $this->description = $description;
        $this->synopsis = $synopsis;
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
     * @param InputDefinition $definition
     * @return CommandConfiguration
     */
    public function withDefinition(InputDefinition $definition)
    {
        $configuration = clone $this;
        $configuration->definition = $definition;

        return $configuration;
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
     * @param string $title
     * @return CommandConfiguration
     */
    public function withProcessTitle($title)
    {
        $configuration = clone $this;
        $configuration->processTitle = $title;

        return $configuration;
    }

    /**
     * @return string
     */
    public function getProcessTitle()
    {
        return $this->processTitle;
    }

    /**
     * @param string $name
     * @return CommandConfiguration
     */
    public function withName($name)
    {
        $this->validateName($name);

        $configuration = clone $this;
        $configuration->name = $name;

        return $configuration;
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
     * @param string $description
     * @return CommandConfiguration
     */
    public function withDescription($description)
    {
        $configuration = clone $this;
        $configuration->description = $description;

        return $configuration;
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
     * @param string $help
     * @return CommandConfiguration
     */
    public function withHelp($help)
    {
        $configuration = clone $this;
        $configuration->help = $help;

        return $configuration;
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
     * @param string[] $aliases
     * @return CommandConfiguration
     */
    public function withAliases(array $aliases)
    {
        foreach ($aliases as $alias) {
            $this->validateName($alias);
        }

        $configuration = clone $this;
        $configuration->aliases = $aliases;

        return $configuration;
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
