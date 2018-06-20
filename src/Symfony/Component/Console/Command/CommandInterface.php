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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for all commands.
 */
interface CommandInterface
{
    /**
     * Ignores validation errors.
     *
     * This is mainly useful for the help command.
     */
    public function ignoreValidationErrors();

    public function setApplication(Application $application = null);

    public function setHelperSet(HelperSet $helperSet);

    /**
     * Gets the helper set.
     *
     * @return HelperSet A HelperSet instance
     */
    public function getHelperSet();

    /**
     * Gets the application instance for this command.
     *
     * @return Application An Application instance
     */
    public function getApplication();

    /**
     * Checks whether the command is enabled or not in the current environment.
     *
     * Override this to check for x or y and return false if the command can not
     * run properly under the current conditions.
     *
     * @return bool
     */
    public function isEnabled();

    /**
     * Runs the command.
     *
     * The code to execute is either defined directly with the
     * setCode() method or by overriding the execute() method
     * in a sub-class.
     *
     * @return int The command exit code
     *
     * @throws \Exception When binding input fails. Bypass this by calling {@link ignoreValidationErrors()}.
     *
     * @see setCode()
     * @see execute()
     */
    public function run(InputInterface $input, OutputInterface $output);

    /**
     * Sets the code to execute when running this command.
     *
     * If this method is used, it overrides the code defined
     * in the execute() method.
     *
     * @param callable $code A callable(InputInterface $input, OutputInterface $output)
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     *
     * @see execute()
     */
    public function setCode($code);

    /**
     * Merges the application definition with the command definition.
     *
     * This method is not part of public API and should not be used directly.
     *
     * @param bool $mergeArgs Whether to merge or not the Application definition arguments to Command definition arguments
     */
    public function mergeApplicationDefinition($mergeArgs = true);

    /**
     * Sets an array of argument and option instances.
     *
     * @param array|InputDefinition $definition An array of argument and option instances or a definition instance
     *
     * @return $this
     */
    public function setDefinition($definition);

    /**
     * Gets the InputDefinition attached to this Command.
     *
     * @return InputDefinition An InputDefinition instance
     */
    public function getDefinition();

    /**
     * Gets the InputDefinition to be used to create XML and Text representations of this Command.
     *
     * Can be overridden to provide the original command representation when it would otherwise
     * be changed by merging with the application InputDefinition.
     *
     * This method is not part of public API and should not be used directly.
     *
     * @return InputDefinition An InputDefinition instance
     */
    public function getNativeDefinition();

    /**
     * Adds an argument.
     *
     * @param string $name        The argument name
     * @param int    $mode        The argument mode: InputArgument::REQUIRED or InputArgument::OPTIONAL
     * @param string $description A description text
     * @param mixed  $default     The default value (for InputArgument::OPTIONAL mode only)
     *
     * @return $this
     */
    public function addArgument($name, $mode = null, $description = '', $default = null);

    /**
     * Adds an option.
     *
     * @param string $name        The option name
     * @param string $shortcut    The shortcut (can be null)
     * @param int    $mode        The option mode: One of the InputOption::VALUE_* constants
     * @param string $description A description text
     * @param mixed  $default     The default value (must be null for InputOption::VALUE_NONE)
     *
     * @return $this
     */
    public function addOption($name, $shortcut = null, $mode = null, $description = '', $default = null);

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
     * @return $this
     *
     * @throws \InvalidArgumentException When the name is invalid
     */
    public function setName($name);

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
     * @return $this
     */
    public function setProcessTitle($title);

    /**
     * Returns the command name.
     *
     * @return string The command name
     */
    public function getName();

    /**
     * Sets the description for the command.
     *
     * @param string $description The description for the command
     *
     * @return $this
     */
    public function setDescription($description);

    /**
     * Returns the description for the command.
     *
     * @return string The description for the command
     */
    public function getDescription();

    /**
     * Sets the help for the command.
     *
     * @param string $help The help for the command
     *
     * @return $this
     */
    public function setHelp($help);

    /**
     * Returns the help for the command.
     *
     * @return string The help for the command
     */
    public function getHelp();

    /**
     * Returns the processed help for the command replacing the %command.name% and
     * %command.full_name% patterns with the real values dynamically.
     *
     * @return string The processed help for the command
     */
    public function getProcessedHelp();

    /**
     * Sets the aliases for the command.
     *
     * @param string[] $aliases An array of aliases for the command
     *
     * @return $this
     *
     * @throws \InvalidArgumentException When an alias is invalid
     */
    public function setAliases($aliases);

    /**
     * Returns the aliases for the command.
     *
     * @return array An array of aliases for the command
     */
    public function getAliases();

    /**
     * Returns the synopsis for the command.
     *
     * @param bool $short Whether to show the short version of the synopsis (with options folded) or not
     *
     * @return string The synopsis
     */
    public function getSynopsis($short = false);

    /**
     * Add a command usage example.
     *
     * @param string $usage The usage, it'll be prefixed with the command name
     *
     * @return $this
     */
    public function addUsage($usage);

    /**
     * Returns alternative usages of the command.
     *
     * @return array
     */
    public function getUsages();

    /**
     * Gets a helper instance by name.
     *
     * @param string $name The helper name
     *
     * @return mixed The helper value
     *
     * @throws \LogicException           if no HelperSet is defined
     * @throws \InvalidArgumentException if the helper is not defined
     */
    public function getHelper($name);

    /**
     * Returns a text representation of the command.
     *
     * @return string A string representing the command
     *
     * @deprecated since version 2.3, to be removed in 3.0.
     */
    public function asText();

    /**
     * Returns an XML representation of the command.
     *
     * @param bool $asDom Whether to return a DOM or an XML string
     *
     * @return string|\DOMDocument An XML string representing the command
     *
     * @deprecated since version 2.3, to be removed in 3.0.
     */
    public function asXml($asDom = false);
}
