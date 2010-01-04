<?php

namespace Symfony\Components\CLI;

use Symfony\Components\CLI\Input\InputInterface;
use Symfony\Components\CLI\Input\ArgvInput;
use Symfony\Components\CLI\Input\ArrayInput;
use Symfony\Components\CLI\Input\Definition;
use Symfony\Components\CLI\Input\Option;
use Symfony\Components\CLI\Input\Argument;
use Symfony\Components\CLI\Output\OutputInterface;
use Symfony\Components\CLI\Output\Output;
use Symfony\Components\CLI\Output\ConsoleOutput;
use Symfony\Components\CLI\Task\Task;
use Symfony\Components\CLI\Task\HelpTask;
use Symfony\Components\CLI\Task\ListTask;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * An Application is the container for a collection of tasks.
 *
 * It is the main entry point of a CLI application.
 *
 * This class is optimized for a standard CLI environment.
 *
 * Usage:
 *
 *     $app = new Application('myapp', '1.0 (stable)');
 *     $app->addTask(new SimpleTask());
 *     $app->run();
 *
 * @package    symfony
 * @subpackage cli
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Application
{
  protected $tasks;
  protected $aliases;
  protected $application;
  protected $wantHelps = false;
  protected $runningTask;
  protected $name;
  protected $version;
  protected $catchExceptions;
  protected $autoExit;
  protected $definition;

  /**
   * Constructor.
   *
   * @param string  $name    The name of the application
   * @param string  $version The version of the application
   */
  public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN')
  {
    $this->name = $name;
    $this->version = $version;
    $this->catchExceptions = true;
    $this->autoExit = true;
    $this->tasks = array();
    $this->aliases = array();

    $this->addTask(new HelpTask());
    $this->addTask(new ListTask());

    $this->definition = new Definition(array(
      new Argument('task', Argument::REQUIRED, 'The task to execute'),

      new Option('--help',           '-h', Option::PARAMETER_NONE, 'Display this help message.'),
      new Option('--quiet',          '-q', Option::PARAMETER_NONE, 'Do not output any message.'),
      new Option('--verbose',        '-v', Option::PARAMETER_NONE, 'Increase verbosity of messages.'),
      new Option('--version',        '-V', Option::PARAMETER_NONE, 'Display this program version.'),
      new Option('--color',          '-c', Option::PARAMETER_NONE, 'Force ANSI color output.'),
      new Option('--no-interaction', '-n', Option::PARAMETER_NONE, 'Do not ask any interactive question.'),
    ));
  }

  /**
   * Runs the current application.
   *
   * @param InputInterface  $input  An Input instance
   * @param OutputInterface $output An Output instance
   *
   * @return integer 0 if everything went fine, or an error code
   */
  public function run(InputInterface $input = null, OutputInterface $output = null)
  {
    if (null === $input)
    {
      $input = new ArgvInput();
    }

    if (null === $output)
    {
      $output = new ConsoleOutput();
    }

    try
    {
      $statusCode = $this->doRun($input, $output);
    }
    catch (\Exception $e)
    {
      if (!$this->catchExceptions)
      {
        throw $e;
      }

      $this->renderException($e, $output);
      $statusCode = $e->getCode();

      $statusCode = is_numeric($statusCode) && $statusCode ? $statusCode : 1;
    }

    if ($this->autoExit)
    {
      // @codeCoverageIgnoreStart
      exit($statusCode);
      // @codeCoverageIgnoreEnd
    }
    else
    {
      return $statusCode;
    }
  }

  /**
   * Runs the current application.
   *
   * @param InputInterface  $input  An Input instance
   * @param OutputInterface $output An Output instance
   *
   * @return integer 0 if everything went fine, or an error code
   */
  public function doRun(InputInterface $input, OutputInterface $output)
  {
    $name = $input->getFirstArgument('task');

    if (false !== $input->hasParameterOption(array('--color', '-c')))
    {
      $output->setDecorated(true);
    }

    if (false !== $input->hasParameterOption(array('--help', '-H')))
    {
      if (!$name)
      {
        $name = 'help';
        $input = new ArrayInput(array('task' => 'help'));
      }
      else
      {
        $this->wantHelps = true;
      }
    }

    if (false !== $input->hasParameterOption(array('--no-interaction', '-n')))
    {
      $input->setInteractive(false);
    }

    if (false !== $input->hasParameterOption(array('--quiet', '-q')))
    {
      $output->setVerbosity(Output::VERBOSITY_QUIET);
    }
    elseif (false !== $input->hasParameterOption(array('--verbose', '-v')))
    {
      $output->setVerbosity(Output::VERBOSITY_VERBOSE);
    }

    if (false !== $input->hasParameterOption(array('--version', '-V')))
    {
      $output->write($this->getLongVersion());

      return 0;
    }

    if (!$name)
    {
      $name = 'list';
      $input = new ArrayInput(array('task' => 'list'));
    }

    // the task name MUST be the first element of the input
    $task = $this->findTask($name);

    $this->runningTask = $task;
    $statusCode = $task->run($input, $output);
    $this->runningTask = null;

    return is_numeric($statusCode) ? $statusCode : 0;
  }

  /**
   * Gets the Definition related to this Application.
   *
   * @return Definition The Definition instance
   */
  public function getDefinition()
  {
    return $this->definition;
  }

  /**
   * Gets the help message.
   *
   * @return string A help message.
   */
  public function getHelp()
  {
    $messages = array(
      $this->getLongVersion(),
      '',
      '<comment>Usage:</comment>',
      sprintf("  %s [options] task [arguments]\n", $this->getName()),
      '<comment>Options:</comment>',
    );

    foreach ($this->definition->getOptions() as $option)
    {
      $messages[] = sprintf('  %-24s %s  %s',
        '<info>--'.$option->getName().'</info>',
        $option->getShortcut() ? '<info>-'.$option->getShortcut().'</info>' : '  ',
        $option->getDescription()
      );
    }

    return implode("\n", $messages);
  }

  /**
   * Sets whether to catch exceptions or not during tasks execution.
   *
   * @param Boolean $boolean Whether to catch exceptions or not during tasks execution
   */
  public function setCatchExceptions($boolean)
  {
    $this->catchExceptions = (Boolean) $boolean;
  }

  /**
   * Sets whether to automatically exit after a task execution or not.
   *
   * @param Boolean $boolean Whether to automatically exit after a task execution or not
   */
  public function setAutoExit($boolean)
  {
    $this->autoExit = (Boolean) $boolean;
  }

  /**
   * Gets the name of the application.
   *
   * @return string The application name
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * Sets the application name.
   *
   * @param string $name The application name
   */
  public function setName($name)
  {
    $this->name = $name;
  }

  /**
   * Gets the application version.
   *
   * @return string The application version
   */
  public function getVersion()
  {
    return $this->version;
  }

  /**
   * Sets the application version.
   *
   * @param string $version The application version
   */
  public function setVersion($version)
  {
    $this->version = $version;
  }

  /**
   * Returns the long version of the application.
   *
   * @return string The long application version
   */
  public function getLongVersion()
  {
    return sprintf('<info>%s</info> version <comment>%s</comment>', $this->getName(), $this->getVersion());
  }

  /**
   * Registers a new task.
   *
   * @param string $name The task name
   *
   * @return Task The newly created task
   */
  public function register($name)
  {
    return $this->addTask(new Task($name));
  }

  /**
   * Adds an array of task objects.
   *
   * @param array  $tasks  An array of tasks
   */
  public function addTasks(array $tasks)
  {
    foreach ($tasks as $task)
    {
      $this->addTask($task);
    }
  }

  /**
   * Adds a task object.
   *
   * If a task with the same name already exists, it will be overridden.
   *
   * @param Task $task A Task object
   *
   * @return Task The registered task
   */
  public function addTask(Task $task)
  {
    $task->setApplication($this);

    $this->tasks[$task->getFullName()] = $task;

    foreach ($task->getAliases() as $alias)
    {
      $this->aliases[$alias] = $task;
    }

    return $task;
  }

  /**
   * Returns a registered task by name or alias.
   *
   * @param string $name The task name or alias
   *
   * @return Task A Task object
   */
  public function getTask($name)
  {
    if (!isset($this->tasks[$name]) && !isset($this->aliases[$name]))
    {
      throw new \InvalidArgumentException(sprintf('The task "%s" does not exist.', $name));
    }

    $task = isset($this->tasks[$name]) ? $this->tasks[$name] : $this->aliases[$name];

    if ($this->wantHelps)
    {
      $this->wantHelps = false;

      $helpTask = $this->getTask('help');
      $helpTask->setTask($task);

      return $helpTask;
    }

    return $task;
  }

  /**
   * Returns true if the task exists, false otherwise
   *
   * @param string $name The task name or alias
   *
   * @return Boolean true if the task exists, false otherwise
   */
  public function hasTask($name)
  {
    return isset($this->tasks[$name]) || isset($this->aliases[$name]);
  }

  /**
   * Returns an array of all unique namespaces used by currently registered tasks.
   *
   * It does not returns the global namespace which always exists.
   *
   * @return array An array of namespaces
   */
  public function getNamespaces()
  {
    $namespaces = array();
    foreach ($this->tasks as $task)
    {
      if ($task->getNamespace())
      {
        $namespaces[$task->getNamespace()] = true;
      }
    }

    return array_keys($namespaces);
  }

  /**
   * Finds a registered namespace by a name or an abbreviation.
   *
   * @return string A registered namespace
   */
  public function findNamespace($namespace)
  {
    $abbrevs = static::getAbbreviations($this->getNamespaces());

    if (!isset($abbrevs[$namespace]))
    {
      throw new \InvalidArgumentException(sprintf('There are no tasks defined in the "%s" namespace.', $namespace));
    }

    if (count($abbrevs[$namespace]) > 1)
    {
      throw new \InvalidArgumentException(sprintf('The namespace "%s" is ambiguous (%s).', $namespace, $this->getAbbreviationSuggestions($abbrevs[$namespace])));
    }

    return $abbrevs[$namespace][0];
  }

  /**
   * Finds a task by name or alias.
   *
   * Contrary to getTask, this task tries to find the best
   * match if you give it an abbreviation of a name or alias.
   *
   * @param  string $name A task name or a task alias
   *
   * @return Task A Task instance
   */
  public function findTask($name)
  {
    // namespace
    $namespace = '';
    if (false !== $pos = strpos($name, ':'))
    {
      $namespace = $this->findNamespace(substr($name, 0, $pos));
      $name = substr($name, $pos + 1);
    }

    $fullName = $namespace ? $namespace.':'.$name : $name;

    // name
    $tasks = array();
    foreach ($this->tasks as $task)
    {
      if ($task->getNamespace() == $namespace)
      {
        $tasks[] = $task->getName();
      }
    }

    $abbrevs = static::getAbbreviations($tasks);
    if (isset($abbrevs[$name]) && 1 == count($abbrevs[$name]))
    {
      return $this->getTask($namespace ? $namespace.':'.$abbrevs[$name][0] : $abbrevs[$name][0]);
    }

    if (isset($abbrevs[$name]) && count($abbrevs[$name]) > 1)
    {
      $suggestions = $this->getAbbreviationSuggestions(array_map(function ($task) use ($namespace) { return $namespace.':'.$task; }, $abbrevs[$name]));

      throw new \InvalidArgumentException(sprintf('Task "%s" is ambiguous (%s).', $fullName, $suggestions));
    }

    // aliases
    $abbrevs = static::getAbbreviations(array_keys($this->aliases));
    if (!isset($abbrevs[$fullName]))
    {
      throw new \InvalidArgumentException(sprintf('Task "%s" is not defined.', $fullName));
    }

    if (count($abbrevs[$fullName]) > 1)
    {
      throw new \InvalidArgumentException(sprintf('Task "%s" is ambiguous (%s).', $fullName, $this->getAbbreviationSuggestions($abbrevs[$fullName])));
    }

    return $this->getTask($abbrevs[$fullName][0]);
  }

  /**
   * Gets the tasks (registered in the given namespace if provided).
   *
   * The array keys are the full names and the values the task instances.
   *
   * @param  string  $namespace A namespace name
   *
   * @return array An array of Task instances
   */
  public function getTasks($namespace = null)
  {
    if (null === $namespace)
    {
      return $this->tasks;
    }

    $tasks = array();
    foreach ($this->tasks as $name => $task)
    {
      if ($namespace === $task->getNamespace())
      {
        $tasks[$name] = $task;
      }
    }

    return $tasks;
  }

  /**
   * Returns an array of possible abbreviations given a set of names.
   *
   * @param array An array of names
   *
   * @return array An array of abbreviations
   */
  static public function getAbbreviations($names)
  {
    $abbrevs = array();
    foreach ($names as $name)
    {
      for ($len = strlen($name) - 1; $len > 0; --$len)
      {
        $abbrev = substr($name, 0, $len);
        if (!isset($abbrevs[$abbrev]))
        {
          $abbrevs[$abbrev] = array($name);
        }
        else
        {
          $abbrevs[$abbrev][] = $name;
        }
      }
    }

    // Non-abbreviations always get entered, even if they aren't unique
    foreach ($names as $name)
    {
      $abbrevs[$name] = array($name);
    }

    return $abbrevs;
  }

  /**
   * Returns a text representation of the Application.
   *
   * @param string $namespace An optional namespace name
   *
   * @return string A string representing the Application
   */
  public function asText($namespace = null)
  {
    $tasks = $namespace ? $this->getTasks($this->findNamespace($namespace)) : $this->tasks;

    $messages = array($this->getHelp(), '');
    if ($namespace)
    {
      $messages[] = sprintf("<comment>Available tasks for the \"%s\" namespace:</comment>", $namespace);
    }
    else
    {
      $messages[] = '<comment>Available tasks:</comment>';
    }

    $width = 0;
    foreach ($tasks as $task)
    {
      $width = strlen($task->getName()) > $width ? strlen($task->getName()) : $width;
    }
    $width += 2;

    // add tasks by namespace
    foreach ($this->sortTasks($tasks) as $space => $tasks)
    {
      if (!$namespace && '_global' !== $space)
      {
        $messages[] = '<comment>'.$space.'</comment>';
      }

      foreach ($tasks as $task)
      {
        $aliases = $task->getAliases() ? '<comment> ('.implode(', ', $task->getAliases()).')</comment>' : '';

        $messages[] = sprintf("  <info>%-${width}s</info> %s%s", ($task->getNamespace() ? ':' : '').$task->getName(), $task->getDescription(), $aliases);
      }
    }

    return implode("\n", $messages);
  }

  /**
   * Returns an XML representation of the Application.
   *
   * @param string $namespace An optional namespace name
   * @param Boolean $asDom Whether to return a DOM or an XML string
   *
   * @return string|DOMDocument An XML string representing the Application
   */
  public function asXml($namespace = null, $asDom = false)
  {
    $tasks = $namespace ? $this->getTasks($this->findNamespace($namespace)) : $this->tasks;

    $dom = new \DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $dom->appendChild($xml = $dom->createElement('symfony'));

    $xml->appendChild($tasksXML = $dom->createElement('tasks'));

    if ($namespace)
    {
      $tasksXML->setAttribute('namespace', $namespace);
    }
    else
    {
      $xml->appendChild($namespacesXML = $dom->createElement('namespaces'));
    }

    // add tasks by namespace
    foreach ($this->sortTasks($tasks) as $space => $tasks)
    {
      if (!$namespace)
      {
        $namespacesXML->appendChild($namespaceArrayXML = $dom->createElement('namespace'));
        $namespaceArrayXML->setAttribute('id', $space);
      }

      foreach ($tasks as $task)
      {
        if (!$namespace)
        {
          $namespaceArrayXML->appendChild($taskXML = $dom->createElement('task'));
          $taskXML->appendChild($dom->createTextNode($task->getName()));
        }

        $taskXML = new \DOMDocument('1.0', 'UTF-8');
        $taskXML->formatOutput = true;
        $taskXML->loadXML($task->asXml());
        $node = $taskXML->getElementsByTagName('task')->item(0);
        $node = $dom->importNode($node, true);

        $tasksXML->appendChild($node);
      }
    }

    return $asDom ? $dom : $dom->saveXml();
  }

  /**
   * Renders a catched exception.
   *
   * @param Exception       $e      An exception instance
   * @param OutputInterface $output An OutputInterface instance
   */
  public function renderException($e, $output)
  {
    $strlen = function ($string)
    {
      return function_exists('mb_strlen') ? mb_strlen($string) : strlen($string);
    };

    $title = sprintf('  [%s]  ', get_class($e));
    $len = $strlen($title);
    $lines = array();
    foreach (explode("\n", $e->getMessage()) as $line)
    {
      $lines[] = sprintf('  %s  ', $line);
      $len = max($strlen($line) + 4, $len);
    }

    $messages = array(str_repeat(' ', $len), $title.str_repeat(' ', $len - $strlen($title)));

    foreach ($lines as $line)
    {
      $messages[] = $line.str_repeat(' ', $len - $strlen($line));
    }

    $messages[] = str_repeat(' ', $len);

    $output->write("\n");
    foreach ($messages as $message)
    {
      $output->write("<error>$message</error>");
    }
    $output->write("\n");

    if (null !== $this->runningTask)
    {
      $output->write(sprintf('<info>%s</info>', sprintf($this->runningTask->getSynopsis(), $this->getName())));
      $output->write("\n");
    }

    if (Output::VERBOSITY_VERBOSE === $output->getVerbosity())
    {
      $output->write('</comment>Exception trace:</comment>');

      // exception related properties
      $trace = $e->getTrace();
      array_unshift($trace, array(
        'function' => '',
        'file'     => $e->getFile() != null ? $e->getFile() : 'n/a',
        'line'     => $e->getLine() != null ? $e->getLine() : 'n/a',
        'args'     => array(),
      ));

      for ($i = 0, $count = count($trace); $i < $count; $i++)
      {
        $class = isset($trace[$i]['class']) ? $trace[$i]['class'] : '';
        $type = isset($trace[$i]['type']) ? $trace[$i]['type'] : '';
        $function = $trace[$i]['function'];
        $file = isset($trace[$i]['file']) ? $trace[$i]['file'] : 'n/a';
        $line = isset($trace[$i]['line']) ? $trace[$i]['line'] : 'n/a';

        $output->write(sprintf(' %s%s%s() at <info>%s:%s</info>', $class, $type, $function, $file, $line));
      }

      $output->write("\n");
    }
  }

  private function sortTasks($tasks)
  {
    $namespacedTasks = array();
    foreach ($tasks as $name => $task)
    {
      $key = $task->getNamespace() ? $task->getNamespace() : '_global';

      if (!isset($namespacedTasks[$key]))
      {
        $namespacedTasks[$key] = array();
      }

      $namespacedTasks[$key][$name] = $task;
    }
    ksort($namespacedTasks);

    foreach ($namespacedTasks as $name => &$tasks)
    {
      ksort($tasks);
    }

    return $namespacedTasks;
  }

  private function getAbbreviationSuggestions($abbrevs)
  {
    return sprintf('%s, %s%s', $abbrevs[0], $abbrevs[1], count($abbrevs) > 2 ? sprintf(' and %d more', count($abbrevs) - 2) : '');
  }
}
