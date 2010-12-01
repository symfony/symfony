<?php

namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Base class for all commands.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Command
{
    protected $name;
    protected $namespace;
    protected $aliases;
    protected $definition;
    protected $help;
    protected $application;
    protected $description;
    protected $ignoreValidationErrors;
    protected $applicationDefinitionMerged;
    protected $code;

    /**
     * Constructor.
     *
     * @param string $name The name of the command
     *
     * @throws \LogicException When the command name is empty
     */
    public function __construct($name = null)
    {
        $this->definition = new InputDefinition();
        $this->ignoreValidationErrors = false;
        $this->applicationDefinitionMerged = false;
        $this->aliases = array();

        if (null !== $name) {
            $this->setName($name);
        }

        $this->configure();

        if (!$this->name) {
            throw new \LogicException('The command name cannot be empty.');
        }
    }

    /**
     * Sets the application instance for this command.
     *
     * @param Application $application An Application instance
     */
    public function setApplication(Application $application = null)
    {
        $this->application = $application;
    }

    /**
     * Configures the current command.
     */
    protected function configure()
    {
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return integer 0 if everything went fine, or an error code
     *
     * @throws \LogicException When this abstract class is not implemented
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        throw new \LogicException('You must override the execute() method in the concrete command class.');
    }

    /**
     * Interacts with the user.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
    }

    /**
     * Initializes the command just after the input has been validated.
     *
     * This is mainly useful when a lot of commands extends one main command
     * where some things need to be initialized based on the input arguments and options.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
    }

    /**
     * Runs the command.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        // add the application arguments and options
        $this->mergeApplicationDefinition();

        // bind the input against the command specific arguments/options
        try {
            $input->bind($this->definition);
        } catch (\Exception $e) {
            if (!$this->ignoreValidationErrors) {
                throw $e;
            }
        }

        $this->initialize($input, $output);

        if ($input->isInteractive()) {
            $this->interact($input, $output);
        }

        $input->validate();

        if ($this->code) {
            return call_user_func($this->code, $input, $output);
        } else {
            return $this->execute($input, $output);
        }
    }

    /**
     * Sets the code to execute when running this command.
     *
     * @param \Closure $code A \Closure
     *
     * @return Command The current instance
     */
    public function setCode(\Closure $code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Merges the application definition with the command definition.
     */
    protected function mergeApplicationDefinition()
    {
        if (null === $this->application || true === $this->applicationDefinitionMerged) {
            return;
        }

        $this->definition->setArguments(array_merge(
            $this->application->getDefinition()->getArguments(),
            $this->definition->getArguments()
        ));

        $this->definition->addOptions($this->application->getDefinition()->getOptions());

        $this->applicationDefinitionMerged = true;
    }

    /**
     * Sets an array of argument and option instances.
     *
     * @param array|Definition $definition An array of argument and option instances or a definition instance
     *
     * @return Command The current instance
     */
    public function setDefinition($definition)
    {
        if ($definition instanceof InputDefinition) {
            $this->definition = $definition;
        } else {
            $this->definition->setDefinition($definition);
        }

        $this->applicationDefinitionMerged = false;

        return $this;
    }

    /**
     * Gets the InputDefinition attached to this Command.
     *
     * @return InputDefinition An InputDefinition instance
     */
    public function getDefinition()
    {
        return $this->definition;
    }

    /**
     * Adds an argument.
     *
     * @param string  $name        The argument name
     * @param integer $mode        The argument mode: InputArgument::REQUIRED or InputArgument::OPTIONAL
     * @param string  $description A description text
     * @param mixed   $default     The default value (for InputArgument::OPTIONAL mode only)
     *
     * @return Command The current instance
     */
    public function addArgument($name, $mode = null, $description = '', $default = null)
    {
        $this->definition->addArgument(new InputArgument($name, $mode, $description, $default));

        return $this;
    }

    /**
     * Adds an option.
     *
     * @param string  $name        The option name
     * @param string  $shortcut    The shortcut (can be null)
     * @param integer $mode        The option mode: One of the InputOption::VALUE_* constants
     * @param string  $description A description text
     * @param mixed   $default     The default value (must be null for InputOption::VALUE_REQUIRED or self::VALUE_NONE)
     *
     * @return Command The current instance
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
     * @return Command The current instance
     *
     * @throws \InvalidArgumentException When command name given is empty
     */
    public function setName($name)
    {
        if (false !== $pos = strrpos($name, ':')) {
            $namespace = substr($name, 0, $pos);
            $name = substr($name, $pos + 1);
        } else {
            $namespace = $this->namespace;
        }

        if (!$name) {
            throw new \InvalidArgumentException('A command name cannot be empty.');
        }

        $this->namespace = $namespace;
        $this->name = $name;

        return $this;
    }

    /**
     * Returns the command namespace.
     *
     * @return string The command namespace
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Returns the command name
     *
     * @return string The command name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the fully qualified command name.
     *
     * @return string The fully qualified command name
     */
    public function getFullName()
    {
        return $this->getNamespace() ? $this->getNamespace().':'.$this->getName() : $this->getName();
    }

    /**
     * Sets the description for the command.
     *
     * @param string $description The description for the command
     *
     * @return Command The current instance
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
     * @return Command The current instance
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
     */
    public function getHelp()
    {
        return $this->help;
    }

    /**
     * Returns the processed help for the command replacing the %command.name% and
     * %command.full_name% patterns with the real values dynamically.
     *
     * @return string  The processed help for the command
     */
    public function getProcessedHelp()
    {
        $name = $this->namespace.':'.$this->name;

        $placeholders = array(
            '%command.name%',
            '%command.full_name%'
        );
        $replacements = array(
            $name,
            $_SERVER['PHP_SELF'].' '.$name
        );

        return str_replace($placeholders, $replacements, $this->getHelp());
    }

    /**
     * Sets the aliases for the command.
     *
     * @param array $aliases An array of aliases for the command
     *
     * @return Command The current instance
     */
    public function setAliases($aliases)
    {
        $this->aliases = $aliases;

        return $this;
    }

    /**
     * Returns the aliases for the command.
     *
     * @return array An array of aliases for the command
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
        return sprintf('%s %s', $this->getFullName(), $this->definition->getSynopsis());
    }

    /**
     * Gets a helper instance by name.
     *
     * @param string $name The helper name
     *
     * @return mixed The helper value
     *
     * @throws \InvalidArgumentException if the helper is not defined
     */
    protected function getHelper($name)
    {
        return $this->application->getHelperSet()->get($name);
    }

    /**
     * Gets a helper instance by name.
     *
     * @param string $name The helper name
     *
     * @return mixed The helper value
     *
     * @throws \InvalidArgumentException if the helper is not defined
     */
    public function __get($name)
    {
        return $this->application->getHelperSet()->get($name);
    }

    /**
     * Returns a text representation of the command.
     *
     * @return string A string representing the command
     */
    public function asText()
    {
        $messages = array(
            '<comment>Usage:</comment>',
            ' '.$this->getSynopsis(),
            '',
        );

        if ($this->getAliases()) {
            $messages[] = '<comment>Aliases:</comment> <info>'.implode(', ', $this->getAliases()).'</info>';
        }

        $messages[] = $this->definition->asText();

        if ($help = $this->getProcessedHelp()) {
            $messages[] = '<comment>Help:</comment>';
            $messages[] = ' '.implode("\n ", explode("\n", $help))."\n";
        }

        return implode("\n", $messages);
    }

    /**
     * Returns an XML representation of the command.
     *
     * @param Boolean $asDom Whether to return a DOM or an XML string
     *
     * @return string|DOMDocument An XML string representing the command
     */
    public function asXml($asDom = false)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->appendChild($commandXML = $dom->createElement('command'));
        $commandXML->setAttribute('id', $this->getFullName());
        $commandXML->setAttribute('namespace', $this->getNamespace() ? $this->getNamespace() : '_global');
        $commandXML->setAttribute('name', $this->getName());

        $commandXML->appendChild($usageXML = $dom->createElement('usage'));
        $usageXML->appendChild($dom->createTextNode(sprintf($this->getSynopsis(), '')));

        $commandXML->appendChild($descriptionXML = $dom->createElement('description'));
        $descriptionXML->appendChild($dom->createTextNode(implode("\n ", explode("\n", $this->getDescription()))));

        $commandXML->appendChild($helpXML = $dom->createElement('help'));
        $help = $this->help;
        $helpXML->appendChild($dom->createTextNode(implode("\n ", explode("\n", $help))));

        $commandXML->appendChild($aliasesXML = $dom->createElement('aliases'));
        foreach ($this->getAliases() as $alias) {
            $aliasesXML->appendChild($aliasXML = $dom->createElement('alias'));
            $aliasXML->appendChild($dom->createTextNode($alias));
        }

        $definition = $this->definition->asXml(true);
        $commandXML->appendChild($dom->importNode($definition->getElementsByTagName('arguments')->item(0), true));
        $commandXML->appendChild($dom->importNode($definition->getElementsByTagName('options')->item(0), true));

        return $asDom ? $dom : $dom->saveXml();
    }
}
