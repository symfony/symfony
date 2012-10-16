<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\ProgressHelper;

/**
 * An Application is the container for a collection of commands.
 *
 * It is the main entry point of a Console application.
 *
 * This class is optimized for a standard CLI environment.
 *
 * Usage:
 *
 *     $app = new Application('myapp', '1.0 (stable)');
 *     $app->add(new SimpleCommand());
 *     $app->run();
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class Application
{
    private $commands;
    private $wantHelps = false;
    private $runningCommand;
    private $name;
    private $version;
    private $catchExceptions;
    private $autoExit;
    private $definition;
    private $helperSet;

    /**
     * Constructor.
     *
     * @param string $name    The name of the application
     * @param string $version The version of the application
     *
     * @api
     */
    public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN')
    {
        $this->name = $name;
        $this->version = $version;
        $this->catchExceptions = true;
        $this->autoExit = true;
        $this->commands = array();
        $this->helperSet = $this->getDefaultHelperSet();
        $this->definition = $this->getDefaultInputDefinition();

        foreach ($this->getDefaultCommands() as $command) {
            $this->add($command);
        }
    }

    /**
     * Runs the current application.
     *
     * @param InputInterface  $input  An Input instance
     * @param OutputInterface $output An Output instance
     *
     * @return integer 0 if everything went fine, or an error code
     *
     * @throws \Exception When doRun returns Exception
     *
     * @api
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        if (null === $input) {
            $input = new ArgvInput();
        }

        if (null === $output) {
            $output = new ConsoleOutput();
        }

        try {
            $statusCode = $this->doRun($input, $output);
        } catch (\Exception $e) {
            if (!$this->catchExceptions) {
                throw $e;
            }

            if ($output instanceof ConsoleOutputInterface) {
                $this->renderException($e, $output->getErrorOutput());
            } else {
                $this->renderException($e, $output);
            }
            $statusCode = $e->getCode();

            $statusCode = is_numeric($statusCode) && $statusCode ? $statusCode : 1;
        }

        if ($this->autoExit) {
            if ($statusCode > 255) {
                $statusCode = 255;
            }
            // @codeCoverageIgnoreStart
            exit($statusCode);
            // @codeCoverageIgnoreEnd
        }

        return $statusCode;
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
        $name = $this->getCommandName($input);

        if (true === $input->hasParameterOption(array('--ansi'))) {
            $output->setDecorated(true);
        } elseif (true === $input->hasParameterOption(array('--no-ansi'))) {
            $output->setDecorated(false);
        }

        if (true === $input->hasParameterOption(array('--help', '-h'))) {
            if (!$name) {
                $name = 'help';
                $input = new ArrayInput(array('command' => 'help'));
            } else {
                $this->wantHelps = true;
            }
        }

        if (true === $input->hasParameterOption(array('--no-interaction', '-n'))) {
            $input->setInteractive(false);
        }

        if (function_exists('posix_isatty') && $this->getHelperSet()->has('dialog')) {
            $inputStream = $this->getHelperSet()->get('dialog')->getInputStream();
            if (!posix_isatty($inputStream)) {
                $input->setInteractive(false);
            }
        }

        if (true === $input->hasParameterOption(array('--quiet', '-q'))) {
            $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        } elseif (true === $input->hasParameterOption(array('--verbose', '-v'))) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }

        if (true === $input->hasParameterOption(array('--version', '-V'))) {
            $output->writeln($this->getLongVersion());

            return 0;
        }

        if (!$name) {
            $name = 'list';
            $input = new ArrayInput(array('command' => 'list'));
        }

        // the command name MUST be the first element of the input
        $command = $this->find($name);

        $this->runningCommand = $command;
        $statusCode = $command->run($input, $output);
        $this->runningCommand = null;

        return is_numeric($statusCode) ? $statusCode : 0;
    }

    /**
     * Set a helper set to be used with the command.
     *
     * @param HelperSet $helperSet The helper set
     *
     * @api
     */
    public function setHelperSet(HelperSet $helperSet)
    {
        $this->helperSet = $helperSet;
    }

    /**
     * Get the helper set associated with the command.
     *
     * @return HelperSet The HelperSet instance associated with this command
     *
     * @api
     */
    public function getHelperSet()
    {
        return $this->helperSet;
    }

    /**
     * Gets the InputDefinition related to this Application.
     *
     * @return InputDefinition The InputDefinition instance
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
            sprintf("  [options] command [arguments]\n"),
            '<comment>Options:</comment>',
        );

        foreach ($this->getDefinition()->getOptions() as $option) {
            $messages[] = sprintf('  %-29s %s %s',
                '<info>--'.$option->getName().'</info>',
                $option->getShortcut() ? '<info>-'.$option->getShortcut().'</info>' : '  ',
                $option->getDescription()
            );
        }

        return implode(PHP_EOL, $messages);
    }

    /**
     * Sets whether to catch exceptions or not during commands execution.
     *
     * @param Boolean $boolean Whether to catch exceptions or not during commands execution
     *
     * @api
     */
    public function setCatchExceptions($boolean)
    {
        $this->catchExceptions = (Boolean) $boolean;
    }

    /**
     * Sets whether to automatically exit after a command execution or not.
     *
     * @param Boolean $boolean Whether to automatically exit after a command execution or not
     *
     * @api
     */
    public function setAutoExit($boolean)
    {
        $this->autoExit = (Boolean) $boolean;
    }

    /**
     * Gets the name of the application.
     *
     * @return string The application name
     *
     * @api
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the application name.
     *
     * @param string $name The application name
     *
     * @api
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Gets the application version.
     *
     * @return string The application version
     *
     * @api
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Sets the application version.
     *
     * @param string $version The application version
     *
     * @api
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * Returns the long version of the application.
     *
     * @return string The long application version
     *
     * @api
     */
    public function getLongVersion()
    {
        if ('UNKNOWN' !== $this->getName() && 'UNKNOWN' !== $this->getVersion()) {
            return sprintf('<info>%s</info> version <comment>%s</comment>', $this->getName(), $this->getVersion());
        }

        return '<info>Console Tool</info>';
    }

    /**
     * Registers a new command.
     *
     * @param string $name The command name
     *
     * @return Command The newly created command
     *
     * @api
     */
    public function register($name)
    {
        return $this->add(new Command($name));
    }

    /**
     * Adds an array of command objects.
     *
     * @param Command[] $commands An array of commands
     *
     * @api
     */
    public function addCommands(array $commands)
    {
        foreach ($commands as $command) {
            $this->add($command);
        }
    }

    /**
     * Adds a command object.
     *
     * If a command with the same name already exists, it will be overridden.
     *
     * @param Command $command A Command object
     *
     * @return Command The registered command
     *
     * @api
     */
    public function add(Command $command)
    {
        $command->setApplication($this);

        if (!$command->isEnabled()) {
            $command->setApplication(null);

            return;
        }

        $this->commands[$command->getName()] = $command;

        foreach ($command->getAliases() as $alias) {
            $this->commands[$alias] = $command;
        }

        return $command;
    }

    /**
     * Returns a registered command by name or alias.
     *
     * @param string $name The command name or alias
     *
     * @return Command A Command object
     *
     * @throws \InvalidArgumentException When command name given does not exist
     *
     * @api
     */
    public function get($name)
    {
        if (!isset($this->commands[$name])) {
            throw new \InvalidArgumentException(sprintf('The command "%s" does not exist.', $name));
        }

        $command = $this->commands[$name];

        if ($this->wantHelps) {
            $this->wantHelps = false;

            $helpCommand = $this->get('help');
            $helpCommand->setCommand($command);

            return $helpCommand;
        }

        return $command;
    }

    /**
     * Returns true if the command exists, false otherwise.
     *
     * @param string $name The command name or alias
     *
     * @return Boolean true if the command exists, false otherwise
     *
     * @api
     */
    public function has($name)
    {
        return isset($this->commands[$name]);
    }

    /**
     * Returns an array of all unique namespaces used by currently registered commands.
     *
     * It does not returns the global namespace which always exists.
     *
     * @return array An array of namespaces
     */
    public function getNamespaces()
    {
        $namespaces = array();
        foreach ($this->commands as $command) {
            $namespaces[] = $this->extractNamespace($command->getName());

            foreach ($command->getAliases() as $alias) {
                $namespaces[] = $this->extractNamespace($alias);
            }
        }

        return array_values(array_unique(array_filter($namespaces)));
    }

    /**
     * Finds a registered namespace by a name or an abbreviation.
     *
     * @param string $namespace A namespace or abbreviation to search for
     *
     * @return string A registered namespace
     *
     * @throws \InvalidArgumentException When namespace is incorrect or ambiguous
     */
    public function findNamespace($namespace)
    {
        $allNamespaces = $this->getNamespaces();
        $namespaces = $this->search($namespace, $allNamespaces);

        if (empty($namespaces)) {
            $message = sprintf('There are no commands defined in the "%s" namespace.', $namespace);

            if ($alternatives = $this->search($namespace, $allNamespaces, true)) {
                $message .= (1 === count($alternatives))
                          ? "\n\nDid you mean this?\n    "
                          : "\n\nDid you mean one of these?\n    "
                ;
                $message .= implode("\n    ", $alternatives);
            }

            throw new \InvalidArgumentException($message);
        }

        if (count($namespaces) > 1) {
            throw new \InvalidArgumentException(sprintf('The namespace "%s" is ambiguous (%s).', $namespace, $this->getAbbreviationSuggestions($namespaces)));
        }

        return $namespaces[0];
    }

    /**
     * Finds a command by name or alias.
     *
     * Contrary to get, this command tries to find the best
     * match if you give it an abbreviation of a name or alias.
     *
     * @param string $name A command name or a command alias
     *
     * @return Command A Command instance
     *
     * @throws \InvalidArgumentException When command name is incorrect or ambiguous
     *
     * @api
     */
    public function find($name)
    {
        $allCommands = array();
        foreach ($this->commands as $command) {
            $allCommands = array_merge(
                $allCommands, array($command->getName()), $command->getAliases()
            );
        }
        $namespace = $this->extractNamespace($name);
        $namespace = $namespace ? $this->findNamespace($namespace) : '';
        $commands = $this->search($name, $allCommands);

        if (empty($commands)) {
            $message = sprintf('Command "%s" is not defined.', $name);

            if ($alternatives = $this->search($name, $allCommands, true)) {
                $message .= (1 === count($alternatives))
                          ? "\n\nDid you mean this?\n    "
                          : "\n\nDid you mean one of these?\n    "
                ;
                $message .= implode("\n    ", $alternatives);
            }

            throw new \InvalidArgumentException($message);
        }

        if (count($commands) > 1) {
            if ($namespaceCommands = preg_grep("/^".$namespace.":.*/", $commands)) {
                $commands = $namespaceCommands;
            }

            throw new \InvalidArgumentException(sprintf('Command "%s" is ambiguous (%s).', $name, $this->getAbbreviationSuggestions($commands)));
        }

        return $this->get($commands[0]);
    }

    /**
     * Gets the commands (registered in the given namespace if provided).
     *
     * The array keys are the full names and the values the command instances.
     *
     * @param string $namespace A namespace name
     *
     * @return array An array of Command instances
     *
     * @api
     */
    public function all($namespace = null)
    {
        if (null === $namespace) {
            return $this->commands;
        }

        $commands = array();
        foreach ($this->commands as $name => $command) {
            if ($namespace === $this->extractNamespace($name, substr_count($namespace, ':') + 1)) {
                $commands[$name] = $command;
            }
        }

        return $commands;
    }

    /**
     * Returns a text representation of the Application.
     *
     * @param string  $namespace An optional namespace name
     * @param boolean $raw       Whether to return raw command list
     *
     * @return string A string representing the Application
     */
    public function asText($namespace = null, $raw = false)
    {
        $commands = $namespace ? $this->all($this->findNamespace($namespace)) : $this->commands;

        $width = 0;
        foreach ($commands as $command) {
            $width = strlen($command->getName()) > $width ? strlen($command->getName()) : $width;
        }
        $width += 2;

        if ($raw) {
            $messages = array();
            foreach ($this->sortCommands($commands) as $space => $commands) {
                foreach ($commands as $name => $command) {
                    $messages[] = sprintf("%-${width}s %s", $name, $command->getDescription());
                }
            }

            return implode(PHP_EOL, $messages);
        }

        $messages = array($this->getHelp(), '');
        if ($namespace) {
            $messages[] = sprintf("<comment>Available commands for the \"%s\" namespace:</comment>", $namespace);
        } else {
            $messages[] = '<comment>Available commands:</comment>';
        }

        // add commands by namespace
        foreach ($this->sortCommands($commands) as $space => $commands) {
            if (!$namespace && '_global' !== $space) {
                $messages[] = '<comment>'.$space.'</comment>';
            }

            foreach ($commands as $name => $command) {
                $messages[] = sprintf("  <info>%-${width}s</info> %s", $name, $command->getDescription());
            }
        }

        return implode(PHP_EOL, $messages);
    }

    /**
     * Returns an XML representation of the Application.
     *
     * @param string  $namespace An optional namespace name
     * @param Boolean $asDom     Whether to return a DOM or an XML string
     *
     * @return string|DOMDocument An XML string representing the Application
     */
    public function asXml($namespace = null, $asDom = false)
    {
        $commands = $namespace ? $this->all($this->findNamespace($namespace)) : $this->commands;

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->appendChild($xml = $dom->createElement('symfony'));

        $xml->appendChild($commandsXML = $dom->createElement('commands'));

        if ($namespace) {
            $commandsXML->setAttribute('namespace', $namespace);
        } else {
            $namespacesXML = $dom->createElement('namespaces');
            $xml->appendChild($namespacesXML);
        }

        // add commands by namespace
        foreach ($this->sortCommands($commands) as $space => $commands) {
            if (!$namespace) {
                $namespaceArrayXML = $dom->createElement('namespace');
                $namespacesXML->appendChild($namespaceArrayXML);
                $namespaceArrayXML->setAttribute('id', $space);
            }

            foreach ($commands as $name => $command) {
                if ($name !== $command->getName()) {
                    continue;
                }

                if (!$namespace) {
                    $commandXML = $dom->createElement('command');
                    $namespaceArrayXML->appendChild($commandXML);
                    $commandXML->appendChild($dom->createTextNode($name));
                }

                $node = $command->asXml(true)->getElementsByTagName('command')->item(0);
                $node = $dom->importNode($node, true);

                $commandsXML->appendChild($node);
            }
        }

        return $asDom ? $dom : $dom->saveXml();
    }

    /**
     * Renders a caught exception.
     *
     * @param Exception       $e      An exception instance
     * @param OutputInterface $output An OutputInterface instance
     */
    public function renderException($e, $output)
    {
        $strlen = function ($string) {
            if (!function_exists('mb_strlen')) {
                return strlen($string);
            }

            if (false === $encoding = mb_detect_encoding($string)) {
                return strlen($string);
            }

            return mb_strlen($string, $encoding);
        };

        do {
            $title = sprintf('  [%s]  ', get_class($e));
            $len = $strlen($title);
            $width = $this->getTerminalWidth() ? $this->getTerminalWidth() - 1 : PHP_INT_MAX;
            $lines = array();
            foreach (preg_split("{\r?\n}", $e->getMessage()) as $line) {
                foreach (str_split($line, $width - 4) as $line) {
                    $lines[] = sprintf('  %s  ', $line);
                    $len = max($strlen($line) + 4, $len);
                }
            }

            $messages = array(str_repeat(' ', $len), $title.str_repeat(' ', max(0, $len - $strlen($title))));

            foreach ($lines as $line) {
                $messages[] = $line.str_repeat(' ', $len - $strlen($line));
            }

            $messages[] = str_repeat(' ', $len);

            $output->writeln("");
            $output->writeln("");
            foreach ($messages as $message) {
                $output->writeln('<error>'.$message.'</error>');
            }
            $output->writeln("");
            $output->writeln("");

            if (OutputInterface::VERBOSITY_VERBOSE === $output->getVerbosity()) {
                $output->writeln('<comment>Exception trace:</comment>');

                // exception related properties
                $trace = $e->getTrace();
                array_unshift($trace, array(
                    'function' => '',
                    'file'     => $e->getFile() != null ? $e->getFile() : 'n/a',
                    'line'     => $e->getLine() != null ? $e->getLine() : 'n/a',
                    'args'     => array(),
                ));

                for ($i = 0, $count = count($trace); $i < $count; $i++) {
                    $class = isset($trace[$i]['class']) ? $trace[$i]['class'] : '';
                    $type = isset($trace[$i]['type']) ? $trace[$i]['type'] : '';
                    $function = $trace[$i]['function'];
                    $file = isset($trace[$i]['file']) ? $trace[$i]['file'] : 'n/a';
                    $line = isset($trace[$i]['line']) ? $trace[$i]['line'] : 'n/a';

                    $output->writeln(sprintf(' %s%s%s() at <info>%s:%s</info>', $class, $type, $function, $file, $line));
                }

                $output->writeln("");
                $output->writeln("");
            }
        } while ($e = $e->getPrevious());

        if (null !== $this->runningCommand) {
            $output->writeln(sprintf('<info>%s</info>', sprintf($this->runningCommand->getSynopsis(), $this->getName())));
            $output->writeln("");
            $output->writeln("");
        }
    }

    /**
     * Tries to figure out the terminal width in which this application runs
     *
     * @return int|null
     */
    protected function getTerminalWidth()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            if ($ansicon = getenv('ANSICON')) {
                return preg_replace('{^(\d+)x.*$}', '$1', $ansicon);
            }

            exec('mode CON', $execData);
            if (preg_match('{columns:\s*(\d+)}i', $execData[4], $matches)) {
                return $matches[1];
            }
        }

        if (preg_match("{rows.(\d+);.columns.(\d+);}i", $this->getSttyColumns(), $match)) {
            return $match[2];
        }
    }

    /**
     * Tries to figure out the terminal height in which this application runs
     *
     * @return int|null
     */
    protected function getTerminalHeight()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            if ($ansicon = getenv('ANSICON')) {
                return preg_replace('{^\d+x\d+ \(\d+x(\d+)\)$}', '$1', trim($ansicon));
            }

            exec('mode CON', $execData);
            if (preg_match('{lines:\s*(\d+)}i', $execData[3], $matches)) {
                return $matches[1];
            }
        }

        if (preg_match("{rows.(\d+);.columns.(\d+);}i", $this->getSttyColumns(), $match)) {
            return $match[1];
        }
    }

    /**
     * Gets the name of the command based on input.
     *
     * @param InputInterface $input The input interface
     *
     * @return string The command name
     */
    protected function getCommandName(InputInterface $input)
    {
        return $input->getFirstArgument();
    }

    /**
     * Gets the default input definition.
     *
     * @return InputDefinition An InputDefinition instance
     */
    protected function getDefaultInputDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('command', InputArgument::REQUIRED, 'The command to execute'),

            new InputOption('--help',           '-h', InputOption::VALUE_NONE, 'Display this help message.'),
            new InputOption('--quiet',          '-q', InputOption::VALUE_NONE, 'Do not output any message.'),
            new InputOption('--verbose',        '-v', InputOption::VALUE_NONE, 'Increase verbosity of messages.'),
            new InputOption('--version',        '-V', InputOption::VALUE_NONE, 'Display this application version.'),
            new InputOption('--ansi',           '',   InputOption::VALUE_NONE, 'Force ANSI output.'),
            new InputOption('--no-ansi',        '',   InputOption::VALUE_NONE, 'Disable ANSI output.'),
            new InputOption('--no-interaction', '-n', InputOption::VALUE_NONE, 'Do not ask any interactive question.'),
        ));
    }

    /**
     * Gets the default commands that should always be available.
     *
     * @return array An array of default Command instances
     */
    protected function getDefaultCommands()
    {
        return array(new HelpCommand(), new ListCommand());
    }

    /**
     * Gets the default helper set with the helpers that should always be available.
     *
     * @return HelperSet A HelperSet instance
     */
    protected function getDefaultHelperSet()
    {
        return new HelperSet(array(
            new FormatterHelper(),
            new DialogHelper(),
            new ProgressHelper(),
        ));
    }

    /**
     * Runs and parses stty -a if it's available, suppressing any error output
     *
     * @return string
     */
    private function getSttyColumns()
    {
        if (!function_exists('proc_open')) {
            return;
        }

        $descriptorspec = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
        $process = proc_open('stty -a | grep columns', $descriptorspec, $pipes, null, null, array('suppress_errors' => true));
        if (is_resource($process)) {
            $info = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return $info;
        }
    }

    /**
     * Sorts commands in alphabetical order.
     *
     * @param array $commands An associative array of commands to sort
     *
     * @return array A sorted array of commands
     */
    private function sortCommands($commands)
    {
        $namespacedCommands = array();
        foreach ($commands as $name => $command) {
            $key = $this->extractNamespace($name, 1);
            if (!$key) {
                $key = '_global';
            }

            $namespacedCommands[$key][$name] = $command;
        }
        ksort($namespacedCommands);

        foreach ($namespacedCommands as &$commands) {
            ksort($commands);
        }

        return $namespacedCommands;
    }

    /**
     * Returns abbreviated suggestions in string format.
     *
     * @param array $abbrevs Abbreviated suggestions to convert
     *
     * @return string A formatted string of abbreviated suggestions
     */
    private function getAbbreviationSuggestions($abbrevs)
    {
        sort($abbrevs);

        return sprintf('%s, %s%s', $abbrevs[0], $abbrevs[1], count($abbrevs) > 2 ? sprintf(' and %d more', count($abbrevs) - 2) : '');
    }

    /**
     * Search for $name in $values, similar names can be matched if $permissive
     * is set to true.
     *
     * @param type $name
     * @param type $values
     * @param type $permissive
     *
     * @return array An array containing matched values.
     */
    private function search($name, $values, $permissive = false)
    {
        $matches = array();
        foreach ($values as $value) {
            if ($value ===  $name) {
                return array($name); // return only this value if it's exactly the same
            }
            if ($this->match($value, $name, $permissive)) {
                $matches[] = $value;
            }
        }

        return array_unique($matches);
    }

    /**
     * Tries to match a command name against another one.
     *
     * @param string   $nameToMatch
     * @param array    $targetName
     * @param Boolean  $permissive
     *
     * @return Boolean Wether or not the names have been matched.
     */
    private function match($nameToMatch, $targetName, $permissive)
    {
        $targetParts = array_filter(explode(':', $targetName));
        $partsToMatch = array_filter(explode(':', $nameToMatch));

        if (!$permissive && 1 === count($targetParts) && 1 < count($partsToMatch)) {
            return false; // strict mode : $nameToMatch has a namespace while $targetName name doesn't
        }
        if ($permissive && 1 === count($targetParts) && 1 < count($partsToMatch) && 0 !== strpos($partsToMatch[0], $targetParts[0])) {
            return false; // permissive mode : the first part of $nameToMatch  doesn't start with the first part of $targetName
        }

        foreach ($targetParts as $pos => $targetPart) {
            if (!empty($partsToMatch[$pos]) && 0 === strpos($partsToMatch[$pos], $targetPart)) {
                continue; // $nameToMatch part starts with $targetName part
            }
            if ($permissive && !empty($partsToMatch[$pos]) && levenshtein($partsToMatch[$pos], $targetPart) <= strlen($partsToMatch[$pos]) / 3) {
                continue; // permissive mode : $nameToMatch part is similar to $targetName part
            }

            return false;
        }

        return true;
    }

    /**
     * Returns the namespace part of the command name.
     *
     * @param string $name  The full name of the command
     * @param string $limit The maximum number of parts of the namespace
     *
     * @return string The namespace of the command
     */
    private function extractNamespace($name, $limit = null)
    {
        $parts = explode(':', $name);
        array_pop($parts);

        return implode(':', null === $limit ? $parts : array_slice($parts, 0, $limit));
    }
}
