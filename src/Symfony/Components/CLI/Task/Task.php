<?php

namespace Symfony\Components\CLI\Task;

use Symfony\Components\CLI\Input\Definition;
use Symfony\Components\CLI\Input\Option;
use Symfony\Components\CLI\Input\Argument;
use Symfony\Components\CLI\Input\InputInterface;
use Symfony\Components\CLI\Output\OutputInterface;
use Symfony\Components\CLI\Output\Formatter;
use Symfony\Components\CLI\Application;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Base class for all tasks.
 *
 * @package    symfony
 * @subpackage cli
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Task
{
  protected $name;
  protected $namespace;
  protected $aliases;
  protected $definition;
  protected $help;
  protected $application;
  protected $description;
  protected $ignoreValidationErrors;
  protected $formatter;
  protected $applicationDefinitionMerged;
  protected $code;

  /**
   * Constructor.
   */
  public function __construct($name = null)
  {
    $this->definition = new Definition();
    $this->ignoreValidationErrors = false;
    $this->applicationDefinitionMerged = false;
    $this->formatter = new Formatter();
    $this->aliases = array();

    if (null !== $name)
    {
      $this->setName($name);
    }

    $this->configure();

    if (!$this->name)
    {
      throw new \LogicException('The task name cannot be empty.');
    }
  }

  /**
   * Sets the application instance for this task.
   *
   * @param Application $application An Application instance
   */
  public function setApplication(Application $application = null)
  {
    $this->application = $application;
  }

  /**
   * Configures the current task.
   */
  protected function configure()
  {
  }

  /**
   * Executes the current task.
   *
   * @param InputInterface  $input  An InputInterface instance
   * @param OutputInterface $output An OutputInterface instance
   *
   * @return integer 0 if everything went fine, or an error code
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    throw new \LogicException('You must override the execute() method in the concrete task class.');
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

  public function run(InputInterface $input, OutputInterface $output)
  {
    // add the application arguments and options
    $this->mergeApplicationDefinition();

    // bind the input against the task specific arguments/options
    try
    {
      $input->bind($this->definition);
    }
    catch (\Exception $e)
    {
      if (!$this->ignoreValidationErrors)
      {
        throw $e;
      }
    }

    if ($input->isInteractive())
    {
      $this->interact($input, $output);
    }

    $input->validate();

    if ($this->code)
    {
      return call_user_func($this->code, $input, $output);
    }
    else
    {
      return $this->execute($input, $output);
    }
  }

  /**
   * Sets the code to execute when running this task.
   *
   * @param \Closure $code A \Closure
   *
   * @return Task The current instance
   */
  public function setCode(\Closure $code)
  {
    $this->code = $code;

    return $this;
  }

  /**
   * Merges the application definition with the task definition.
   */
  protected function mergeApplicationDefinition()
  {
    if (null === $this->application || true === $this->applicationDefinitionMerged)
    {
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
   * @return Task The current instance
   */
  public function setDefinition($definition)
  {
    if ($definition instanceof Definition)
    {
      $this->definition = $definition;
    }
    else
    {
      $this->definition->setDefinition($definition);
    }

    $this->applicationDefinitionMerged = false;

    return $this;
  }

  /**
   * Adds an argument.
   *
   * @param string  $name        The argument name
   * @param integer $mode        The argument mode: Argument::REQUIRED or Argument::OPTIONAL
   * @param string  $description A description text
   * @param mixed   $default     The default value (for Argument::OPTIONAL mode only)
   *
   * @return Task The current instance
   */
  public function addArgument($name, $mode = null, $description = '', $default = null)
  {
    $this->definition->addArgument(new Argument($name, $mode, $description, $default));

    return $this;
  }

  /**
   * Adds an option.
   *
   * @param string  $name        The option name
   * @param string  $shortcut    The shortcut (can be null)
   * @param integer $mode        The option mode: self::PARAMETER_REQUIRED, self::PARAMETER_NONE or self::PARAMETER_OPTIONAL
   * @param string  $description A description text
   * @param mixed   $default     The default value (must be null for self::PARAMETER_REQUIRED or self::PARAMETER_NONE)
   *
   * @return Task The current instance
   */
  public function addOption($name, $shortcut = null, $mode = null, $description = '', $default = null)
  {
    $this->definition->addOption(new Option($name, $shortcut, $mode, $description, $default));

    return $this;
  }

  /**
   * Sets the name of the task.
   *
   * This method can set both the namespace and the name if
   * you separate them by a colon (:)
   *
   *     $task->setName('foo:bar');
   *
   * @param string $name The task name
   *
   * @return Task The current instance
   */
  public function setName($name)
  {
    if (false !== $pos = strpos($name, ':'))
    {
      $namespace = substr($name, 0, $pos);
      $name = substr($name, $pos + 1);
    }
    else
    {
      $namespace = $this->namespace;
    }

    if (!$name)
    {
      throw new \InvalidArgumentException('A task name cannot be empty');
    }

    $this->namespace = $namespace;
    $this->name = $name;

    return $this;
  }

  /**
   * Returns the task namespace.
   *
   * @return string The task namespace
   */
  public function getNamespace()
  {
    return $this->namespace;
  }

  /**
   * Returns the task name
   *
   * @return string The task name
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * Returns the fully qualified task name.
   *
   * @return string The fully qualified task name
   */
  public function getFullName()
  {
    return $this->getNamespace() ? $this->getNamespace().':'.$this->getName() : $this->getName();
  }

  /**
   * Sets the description for the task.
   *
   * @param string $description The description for the task
   *
   * @return Task The current instance
   */
  public function setDescription($description)
  {
    $this->description = $description;

    return $this;
  }

  /**
   * Returns the description for the task.
   *
   * @return string The description for the task
   */
  public function getDescription()
  {
    return $this->description;
  }

  /**
   * Sets the help for the task.
   *
   * @param string $help The help for the task
   *
   * @return Task The current instance
   */
  public function setHelp($help)
  {
    $this->help = $help;

    return $this;
  }

  /**
   * Returns the help for the task.
   *
   * @return string The help for the task
   */
  public function getHelp()
  {
    return $this->help;
  }

  /**
   * Sets the aliases for the task.
   *
   * @param array $aliases An array of aliases for the task
   *
   * @return Task The current instance
   */
  public function setAliases($aliases)
  {
    $this->aliases = $aliases;

    return $this;
  }

  /**
   * Returns the aliases for the task.
   *
   * @return array An array of aliases for the task
   */
  public function getAliases()
  {
    return $this->aliases;
  }

  /**
   * Returns the synopsis for the task.
   *
   * @return string The synopsis
   */
  public function getSynopsis()
  {
    return sprintf('%%s %s %s', $this->getFullName(), $this->definition->getSynopsis());
  }

  /**
   * Asks a question to the user.
   *
   * @param OutputInterface $output
   * @param string|array    $question The question to ask
   * @param string          $default  The default answer if none is given by the user
   *
   * @param string The user answer
   */
  static public function ask(OutputInterface $output, $question, $default = null)
  {
    // @codeCoverageIgnoreStart
    $output->write($question);

    $ret = trim(fgets(STDIN));

    return $ret ? $ret : $default;
    // @codeCoverageIgnoreEnd
  }

  /**
   * Asks a confirmation to the user.
   *
   * The question will be asked until the user answer by nothing, yes, or no.
   *
   * @param OutputInterface $output
   * @param string|array    $question The question to ask
   * @param Boolean         $default  The default answer if the user enters nothing
   *
   * @param Boolean true if the user has confirmed, false otherwise
   */
  static public function askConfirmation(OutputInterface $output, $question, $default = true)
  {
    // @codeCoverageIgnoreStart
    $answer = 'z';
    while ($answer && !in_array(strtolower($answer[0]), array('y', 'n')))
    {
      $answer = static::ask($output, $question);
    }

    if (false === $default)
    {
      return $answer && 'y' == strtolower($answer[0]);
    }
    else
    {
      return !$answer || 'y' == strtolower($answer[0]);
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Asks for a value and validates the response.
   *
   * @param OutputInterface $output
   * @param string|array    $question
   * @param Closure         $validator
   * @param integer         $attempts Max number of times to ask before giving up (false by default, which means infinite)
   *
   * @return mixed
   */
  static public function askAndValidate(OutputInterface $output, $question, \Closure $validator, $attempts = false)
  {
    // @codeCoverageIgnoreStart
    $error = null;
    while (false === $attempts || $attempts--)
    {
      if (null !== $error)
      {
        $output->write($this->formatter->formatBlock($error->getMessage(), 'error'));
      }

      $value = static::ask($output, $question, null);

      try
      {
        return $validator($value);
      }
      catch (\Exception $error)
      {
      }
    }

    throw $error;
    // @codeCoverageIgnoreEnd
  }

  /**
   * Returns a text representation of the task.
   *
   * @return string A string representing the task
   */
  public function asText()
  {
    $messages = array(
      '<comment>Usage:</comment>',
      sprintf(' '.$this->getSynopsis(), null === $this->application ? '' : $this->application->getName()),
      '',
    );

    if ($this->getAliases())
    {
      $messages[] = '<comment>Aliases:</comment> <info>'.implode(', ', $this->getAliases()).'</info>';
    }

    $messages[] = $this->definition->asText();

    if ($help = $this->getHelp())
    {
      $messages[] = '<comment>Help:</comment>';
      $messages[] = ' '.implode("\n ", explode("\n", $help))."\n";
    }

    return implode("\n", $messages);
  }

  /**
   * Returns an XML representation of the task.
   *
   * @param Boolean $asDom Whether to return a DOM or an XML string
   *
   * @return string|DOMDocument An XML string representing the task
   */
  public function asXml($asDom = false)
  {
    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $dom->appendChild($taskXML = $dom->createElement('task'));
    $taskXML->setAttribute('id', $this->getFullName());
    $taskXML->setAttribute('namespace', $this->getNamespace() ? $this->getNamespace() : '_global');
    $taskXML->setAttribute('name', $this->getName());

    $taskXML->appendChild($usageXML = $dom->createElement('usage'));
    $usageXML->appendChild($dom->createTextNode(sprintf($this->getSynopsis(), '')));

    $taskXML->appendChild($descriptionXML = $dom->createElement('description'));
    $descriptionXML->appendChild($dom->createTextNode(implode("\n ", explode("\n", $this->getDescription()))));

    $taskXML->appendChild($helpXML = $dom->createElement('help'));
    $help = $this->help;
    $helpXML->appendChild($dom->createTextNode(implode("\n ", explode("\n", $help))));

    $taskXML->appendChild($aliasesXML = $dom->createElement('aliases'));
    foreach ($this->getAliases() as $alias)
    {
      $aliasesXML->appendChild($aliasXML = $dom->createElement('alias'));
      $aliasXML->appendChild($dom->createTextNode($alias));
    }

    $definition = $this->definition->asXml(true);
    $taskXML->appendChild($dom->importNode($definition->getElementsByTagName('arguments')->item(0), true));
    $taskXML->appendChild($dom->importNode($definition->getElementsByTagName('options')->item(0), true));

    return $asDom ? $dom : $dom->saveXml();
  }
}
