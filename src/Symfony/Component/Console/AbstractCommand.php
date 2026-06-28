<?php

namespace Symfony\Component\Console;
use Symfony\Component\Console\ArgumentResolver\ArgumentResolverInterface;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Event\ConsoleAlarmEvent;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\NamespaceNotFoundException;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputAwareInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\SignalRegistry\SignalRegistry;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Exception\RuntimeException;

abstract class AbstractCommand
{
    protected ?Command $runningCommand = null;
    protected ?InputDefinition $definition = null;
    protected ?CommandLoaderInterface $commandLoader = null;
    protected ?ArgumentResolverInterface $argumentResolver = null;
    private ?EventDispatcherInterface $dispatcher = null;
    private array $commands = [];
    protected bool $wantHelps = false;
    protected string $defaultCommand;
    private Terminal $terminal;
    private ?SignalRegistry $signalRegistry = null;
    private array $signalsToDispatchEvent = [];
    private ?int $alarmInterval = null;

    protected function __construct(
    ){
        $this->defaultCommand = 'list';
        $this->terminal = new Terminal();
        if (\defined('SIGINT') && SignalRegistry::isSupported()) {
            $this->signalRegistry = new SignalRegistry();
            $this->signalsToDispatchEvent = [\SIGINT, \SIGQUIT, \SIGTERM, \SIGUSR1, \SIGUSR2, \SIGALRM];
        }
    }

    public function getSignalRegistry(): SignalRegistry
    {
        if (!$this->signalRegistry) {
            throw new RuntimeException('Signals are not supported. Make sure that the "pcntl" extension is installed and that "pcntl_*" functions are not disabled by your php.ini\'s "disable_functions" directive.');
        }

        return $this->signalRegistry;
    }

    public function setSignalsToDispatchEvent(int ...$signalsToDispatchEvent): void
    {
        $this->signalsToDispatchEvent = $signalsToDispatchEvent;
    }

    /**
     * Sets the interval to schedule a SIGALRM signal in seconds.
     */
    public function setAlarmInterval(?int $seconds): void
    {
        $this->alarmInterval = $seconds;
        $this->scheduleAlarm();
    }

    /**
     * Gets the interval in seconds on which a SIGALRM signal is dispatched.
     */
    public function getAlarmInterval(): ?int
    {
        return $this->alarmInterval;
    }

    private function scheduleAlarm(): void
    {
        if (null !== $this->alarmInterval) {
            $this->getSignalRegistry()->scheduleAlarm($this->alarmInterval);
        }
    }


    /**
     * @final
     */
    public function setArgumentResolver(ArgumentResolverInterface $argumentResolver): void
    {
        $this->argumentResolver = $argumentResolver;
    }

    public function getArgumentResolver(): ?ArgumentResolverInterface
    {
        return $this->argumentResolver;
    }

    public function setCommandLoader(CommandLoaderInterface $commandLoader): void
    {
        $this->commandLoader = $commandLoader;
    }

    public function getDefinition(): InputDefinition
    {
        return $this->definition;
    }

    /**
     * Runs the a command
     *
     * @return int 0 if everything went fine, or an error code
     */
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Makes ArgvInput::getFirstArgument() able to distinguish an option from an argument.
            $input->bind($this->getDefinition());
        } catch (ExceptionInterface) {
            // Errors must be ignored, full binding/validation happens later when the command is known.
        }

        $name = $this->getCommandName($input);
        if (true === $input->hasParameterOption(['--help', '-h'], true)) {
            if (!$name) {
                $name = 'help';
                $input = new ArrayInput(['command_name' => $this->defaultCommand]);
            } else {
                $this->wantHelps = true;
            }
        }

        if (!$name) {
            $name = $this->defaultCommand;
            $definition = $this->getDefinition();
            $definition->setArguments(array_merge(
                $definition->getArguments(),
                [
                    'command' => new InputArgument('command', InputArgument::OPTIONAL, $definition->getArgument('command')->getDescription(), $name),
                ]
            ));
        }

        try {
            $this->runningCommand = null;
            // the command name MUST be the first element of the input
            $command = $this->find($name);
        } catch (\Throwable $e) {
            if (($e instanceof CommandNotFoundException && !$e instanceof NamespaceNotFoundException) && 1 === \count($alternatives = $e->getAlternatives()) && $input->isInteractive()) {
                $alternative = $alternatives[0];

                $style = new SymfonyStyle($input, $output);
                $output->writeln('');
                $formattedBlock = (new FormatterHelper())->formatBlock(\sprintf('Command "%s" is not defined.', $name), 'error', true);
                $output->writeln($formattedBlock);
                if (!$style->confirm(\sprintf('Do you want to run "%s" instead? ', $alternative), false)) {
                    if (null !== $this->dispatcher) {
                        $event = new ConsoleErrorEvent($input, $output, $e);
                        $this->dispatcher->dispatch($event, ConsoleEvents::ERROR);

                        return $event->getExitCode();
                    }

                    return 1;
                }

                $command = $this->find($alternative);
            } else {
                if (null !== $this->dispatcher) {
                    $event = new ConsoleErrorEvent($input, $output, $e);
                    $this->dispatcher->dispatch($event, ConsoleEvents::ERROR);

                    if (0 === $event->getExitCode()) {
                        return 0;
                    }

                    $e = $event->getError();
                }

                try {
                    if ($e instanceof CommandNotFoundException && $namespace = $this->findNamespace($name)) {
                        $helper = new DescriptorHelper();
                        $helper->describe($output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output, $this, [
                            'format' => 'txt',
                            'raw_text' => false,
                            'namespace' => $namespace,
                            'short' => false,
                        ]);

                        return isset($event) ? $event->getExitCode() : 1;
                    }

                    throw $e;
                } catch (NamespaceNotFoundException) {
                    throw $e;
                }
            }
        }

        if ($command instanceof LazyCommand) {
            $command = $command->getCommand();
        }

        $this->runningCommand = $command;
        $exitCode = $this->doRunCommand($command, $input, $output);
        $this->runningCommand = null;

        return $exitCode;
    }

    /**
     * Gets the name of the command based on input.
     */
    protected function getCommandName(InputInterface $input): ?string
    {
        return $input->getFirstArgument();
    }

    /**
     * Finds a command by name or alias.
     *
     * Contrary to get, this command tries to find the best
     * match if you give it an abbreviation of a name or alias.
     *
     * @throws CommandNotFoundException When command name is incorrect or ambiguous
     */
    public function find(string $name): Command
    {
        $aliases = [];

        foreach ($this->commands as $command) {
            foreach ($command->getAliases() as $alias) {
                if (!$this->has($alias)) {
                    $this->commands[$alias] = $command;
                }
            }
        }

        if ($this->has($name)) {
            return $this->get($name);
        }

        $allCommands = $this->commandLoader ? array_merge($this->commandLoader->getNames(), array_keys($this->commands)) : array_keys($this->commands);
        $expr = implode('[^:]*:', array_map('preg_quote', explode(':', $name))).'[^:]*';
        $commands = preg_grep('{^'.$expr.'}', $allCommands);

        if (!$commands) {
            $commands = preg_grep('{^'.$expr.'}i', $allCommands);
        }

        sort($commands);

        // if no commands matched or we just matched namespaces
        if (!$commands || \count(preg_grep('{^'.$expr.'$}i', $commands)) < 1) {
            if (false !== $pos = strrpos($name, ':')) {
                // check if a namespace exists and contains commands
                $this->findNamespace(substr($name, 0, $pos));
            }

            $message = \sprintf('Command "%s" is not defined.', $name);

            if ($alternatives = $this->findAlternatives($name, $allCommands)) {
                $wantHelps = $this->wantHelps;

                $this->wantHelps = false;

                // // remove hidden commands
                if ($alternatives = array_filter($alternatives, fn ($name) => !$this->get($name)->isHidden())) {
                     $message .= \sprintf("\n\nDid you mean %s?\n    %s", 1 === \count($alternatives) ? 'this' : 'one of these', implode("\n    ", $alternatives));
                }
                $this->wantHelps = $wantHelps;
            }

            throw new CommandNotFoundException($message, array_values($alternatives));
        }

        // filter out aliases for commands which are already on the list
        if (\count($commands) > 1) {
            $commandList = $this->commandLoader ? array_merge(array_flip($this->commandLoader->getNames()), $this->commands) : $this->commands;
            $commands = array_unique(array_filter($commands, function ($nameOrAlias) use (&$commandList, $commands, &$aliases) {
                if (!$commandList[$nameOrAlias] instanceof Command) {
                    $commandList[$nameOrAlias] = $this->commandLoader->get($nameOrAlias);
                }

                $commandName = $commandList[$nameOrAlias]->getName();

                $aliases[$nameOrAlias] = $commandName;

                return $commandName === $nameOrAlias || !\in_array($commandName, $commands, true);
            }));
        }

        // check whether all commands left are aliases to the same one
        if (\count($commands) > 1) {
            $uniqueCommands = array_unique(array_map(function ($nameOrAlias) use (&$commandList) {
                if (!$commandList[$nameOrAlias] instanceof Command) {
                    $commandList[$nameOrAlias] = $this->commandLoader->get($nameOrAlias);
                }

                return $commandList[$nameOrAlias]->getName();
            }, $commands));

            if (1 === \count($uniqueCommands)) {
                $commands = [reset($uniqueCommands)];
            }
        }

        if (\count($commands) > 1) {
            sort($commands);
            $usableWidth = $this->terminal->getWidth() - 10;
            $abbrevs = array_values($commands);
            $maxLen = 0;
            foreach ($abbrevs as $abbrev) {
                $maxLen = max(Helper::width($abbrev), $maxLen);
            }
            $abbrevs = array_map(static function ($cmd) use ($commandList, $usableWidth, $maxLen, &$commands) {
                if ($commandList[$cmd]->isHidden()) {
                    unset($commands[array_search($cmd, $commands)]);

                    return false;
                }

                $abbrev = str_pad($cmd, $maxLen, ' ').' '.$commandList[$cmd]->getDescription();

                return Helper::width($abbrev) > $usableWidth ? Helper::substr($abbrev, 0, $usableWidth - 3).'...' : $abbrev;
            }, array_values($commands));

            if (\count($commands) > 1) {
                $suggestions = $this->getAbbreviationSuggestions(array_filter($abbrevs));

                throw new CommandNotFoundException(\sprintf("Command \"%s\" is ambiguous.\nDid you mean one of these?\n%s", $name, $suggestions), array_values($commands));
            }
        }

        $command = $commands ? $this->get(reset($commands)) : null;

        if (!$command || $command->isHidden()) {
            throw new CommandNotFoundException(\sprintf('The command "%s" does not exist.', $name));
        }

        return $command;
    }

    /**
     * Runs the current command.
     *
     * If an event dispatcher has been attached to the application,
     * events are also dispatched during the life-cycle of the command.
     *
     * @return int 0 if everything went fine, or an error code
     */
    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        foreach ($command->getHelperSet() as $helper) {
            if ($helper instanceof InputAwareInterface) {
                $helper->setInput($input);
            }
        }

        $registeredSignals = false;
        if (($commandSignals = $command->getSubscribedSignals()) || $this->dispatcher && $this->signalsToDispatchEvent) {
            $signalRegistry = $this->getSignalRegistry();

            $registeredSignals = true;
            $this->getSignalRegistry()->pushCurrentHandlers();

            if ($this->dispatcher) {
                // We register application signals, so that we can dispatch the event
                foreach ($this->signalsToDispatchEvent as $signal) {
                    $signalEvent = new ConsoleSignalEvent($command, $input, $output, $signal);
                    $alarmEvent = \SIGALRM === $signal ? new ConsoleAlarmEvent($command, $input, $output) : null;

                    $signalRegistry->register($signal, function ($signal) use ($signalEvent, $alarmEvent, $command, $commandSignals, $input, $output) {
                        $this->dispatcher->dispatch($signalEvent, ConsoleEvents::SIGNAL);
                        $exitCode = $signalEvent->getExitCode();

                        if (null !== $alarmEvent) {
                            if (false !== $exitCode) {
                                $alarmEvent->setExitCode($exitCode);
                            } else {
                                $alarmEvent->abortExit();
                            }
                            $this->dispatcher->dispatch($alarmEvent);
                            $exitCode = $alarmEvent->getExitCode();
                        }

                        // If the command is signalable, we call the handleSignal() method
                        if (\in_array($signal, $commandSignals, true)) {
                            $exitCode = $command->handleSignal($signal, $exitCode);
                        }

                        if (\SIGALRM === $signal) {
                            $this->scheduleAlarm();
                        }

                        if (false !== $exitCode) {
                            $event = new ConsoleTerminateEvent($command, $input, $output, $exitCode, $signal);
                            $this->dispatcher->dispatch($event, ConsoleEvents::TERMINATE);

                            exit($event->getExitCode());
                        }
                    });
                }

                // then we register command signals, but not if already handled after the dispatcher
                $commandSignals = array_diff($commandSignals, $this->signalsToDispatchEvent);
            }

            foreach ($commandSignals as $signal) {
                $signalRegistry->register($signal, function (int $signal) use ($command): void {
                    if (\SIGALRM === $signal) {
                        $this->scheduleAlarm();
                    }

                    if (false !== $exitCode = $command->handleSignal($signal)) {
                        exit($exitCode);
                    }
                });
            }
        }

        if (null === $this->dispatcher) {
            try {
                return $command->run($input, $output);
            } finally {
                if ($registeredSignals) {
                    $this->getSignalRegistry()->popPreviousHandlers();
                }
            }
        }

        // bind before the console.command event, so the listeners have access to input options/arguments
        try {
            $command->mergeApplicationDefinition();
            $input->bind($command->getDefinition());
        } catch (ExceptionInterface) {
            // ignore invalid options/arguments for now, to allow the event listeners to customize the InputDefinition
        }

        $event = new ConsoleCommandEvent($command, $input, $output);
        $e = null;

        try {
            $this->dispatcher->dispatch($event, ConsoleEvents::COMMAND);

            if ($event->commandShouldRun()) {
                $exitCode = $command->run($input, $output);
            } else {
                $exitCode = ConsoleCommandEvent::RETURN_CODE_DISABLED;
            }
        } catch (\Throwable $e) {
            $event = new ConsoleErrorEvent($input, $output, $e, $command);
            $this->dispatcher->dispatch($event, ConsoleEvents::ERROR);
            $e = $event->getError();

            if (0 === $exitCode = $event->getExitCode()) {
                $e = null;
            }
        } finally {
            if ($registeredSignals) {
                $this->getSignalRegistry()->popPreviousHandlers();
            }
        }

        $event = new ConsoleTerminateEvent($command, $input, $output, $exitCode);
        $this->dispatcher->dispatch($event, ConsoleEvents::TERMINATE);

        if (null !== $e) {
            throw $e;
        }

        return $event->getExitCode();
    }

    /**
     * Registers a new command.
     */
    public function register(string $name): Command
    {
        return $this->addCommand(new Command($name));
    }

    /**
     * Adds an array of command objects.
     *
     * If a Command is not enabled it will not be added.
     *
     * @param callable[]|Command[] $commands An array of commands
     */
    public function addCommands(array $commands): void
    {
        foreach ($commands as $command) {
            $this->addCommand($command);
        }
    }

    /**
     * Adds a command object.
     *
     * If a command with the same name already exists, it will be overridden.
     * If the command is not enabled it will not be added.
     */
    public function addCommand(callable|Command $command): ?Command
    {
        if (!$command instanceof Command) {
            $command = new Command(null, $command);
        }

        $command->setApplication($this);

        if (!$command->isEnabled()) {
            $command->setApplication(null);

            return null;
        }

        if (!$command instanceof LazyCommand) {
            // Will throw if the command is not correctly initialized.
            $command->getDefinition();
        }

        if (!$command->getName()) {
            throw new LogicException(\sprintf('The command defined in "%s" cannot have an empty name.', get_debug_type($command)));
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
     * @throws CommandNotFoundException When given command name does not exist
     */
    public function get(string $name): Command
    {
        if (!$this->has($name)) {
            throw new CommandNotFoundException(\sprintf('The command "%s" does not exist.', $name));
        }

        // When the command has a different name than the one used at the command loader level
        if (!isset($this->commands[$name])) {
            throw new CommandNotFoundException(\sprintf('The "%s" command cannot be found because it is registered under multiple names. Make sure you don\'t set a different name via constructor or "setName()".', $name));
        }

        $command = $this->commands[$name];

        return $command;
    }

    /**
     * Returns true if the command exists, false otherwise.
     */
    public function has(string $name): bool
    {
        return isset($this->commands[$name]) || ($this->commandLoader?->has($name) && $this->addCommand($this->commandLoader->get($name)));
    }

    /**
     * @final
     */
    public function setDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    public function getDispatcher(): ?EventDispatcherInterface
    {
        return $this->dispatcher;
    }

    /**
     * Gets the commands (registered in the given namespace if provided).
     *
     * The array keys are the full names and the values the command instances.
     *
     * @return Command[]
     */
    public function all(?string $namespace = null): array
    {
        if (null === $namespace) {
            if (!$this->commandLoader) {
                return $this->commands;
            }

            $commands = $this->commands;
            foreach ($this->commandLoader->getNames() as $name) {
                if (!isset($commands[$name]) && $this->has($name)) {
                    $commands[$name] = $this->get($name);
                }
            }

            return $commands;
        }

        $commands = [];
        foreach ($this->commands as $name => $command) {
            if ($namespace === $this->extractNamespace($name, substr_count($namespace, ':') + 1)) {
                $commands[$name] = $command;
            }
        }

        if ($this->commandLoader) {
            foreach ($this->commandLoader->getNames() as $name) {
                if (!isset($commands[$name]) && $namespace === $this->extractNamespace($name, substr_count($namespace, ':') + 1) && $this->has($name)) {
                    $commands[$name] = $this->get($name);
                }
            }
        }

        return $commands;
    }

    /**
     * Finds alternative of $name among $collection,
     * if nothing is found in $collection, try in $abbrevs.
     *
     * @return string[]
     */
    private function findAlternatives(string $name, iterable $collection): array
    {
        $threshold = 1e3;
        $alternatives = [];

        $collectionParts = [];
        foreach ($collection as $item) {
            $collectionParts[$item] = explode(':', $item);
        }

        foreach (explode(':', $name) as $i => $subname) {
            foreach ($collectionParts as $collectionName => $parts) {
                $exists = isset($alternatives[$collectionName]);
                if (!isset($parts[$i]) && $exists) {
                    $alternatives[$collectionName] += $threshold;
                    continue;
                } elseif (!isset($parts[$i])) {
                    continue;
                }

                $lev = levenshtein($subname, $parts[$i]);
                if ($lev <= \strlen($subname) / 3 || '' !== $subname && str_contains($parts[$i], $subname)) {
                    $alternatives[$collectionName] = $exists ? $alternatives[$collectionName] + $lev : $lev;
                } elseif ($exists) {
                    $alternatives[$collectionName] += $threshold;
                }
            }
        }

        foreach ($collection as $item) {
            $lev = levenshtein($name, $item);
            if ($lev <= \strlen($name) / 3 || str_contains($item, $name)) {
                $alternatives[$item] = isset($alternatives[$item]) ? $alternatives[$item] - $lev : $lev;
            }
        }

        $alternatives = array_filter($alternatives, static fn ($lev) => $lev < 2 * $threshold);
        ksort($alternatives, \SORT_NATURAL | \SORT_FLAG_CASE);

        return array_keys($alternatives);
    }

    /**
     * Finds a registered namespace by a name or an abbreviation.
     *
     * @throws NamespaceNotFoundException When namespace is incorrect or ambiguous
     */
    public function findNamespace(string $namespace): string
    {
        $allNamespaces = $this->getNamespaces();
        $expr = implode('[^:]*:', array_map('preg_quote', explode(':', $namespace))).'[^:]*';
        $namespaces = preg_grep('{^'.$expr.'}', $allNamespaces);

        if (!$namespaces) {
            $message = \sprintf('There are no commands defined in the "%s" namespace.', $namespace);

            if ($alternatives = $this->findAlternatives($namespace, $allNamespaces)) {
                if (1 == \count($alternatives)) {
                    $message .= "\n\nDid you mean this?\n    ";
                } else {
                    $message .= "\n\nDid you mean one of these?\n    ";
                }

                $message .= implode("\n    ", $alternatives);
            }

            throw new NamespaceNotFoundException($message, $alternatives);
        }

        $exact = \in_array($namespace, $namespaces, true);
        if (\count($namespaces) > 1 && !$exact) {
            sort($namespaces);

            throw new NamespaceNotFoundException(\sprintf("The namespace \"%s\" is ambiguous.\nDid you mean one of these?\n%s.", $namespace, $this->getAbbreviationSuggestions(array_values($namespaces))), array_values($namespaces));
        }

        return $exact ? $namespace : reset($namespaces);
    }

    /**
     * Returns an array of all unique namespaces used by currently registered commands.
     *
     * It does not return the global namespace which always exists.
     *
     * @return string[]
     */
    public function getNamespaces(): array
    {
        $namespaces = [];
        foreach ($this->all() as $command) {
            if ($command->isHidden()) {
                continue;
            }

            $namespaces[] = $this->extractAllNamespaces($command->getName());

            foreach ($command->getAliases() as $alias) {
                $namespaces[] = $this->extractAllNamespaces($alias);
            }
        }

        return array_values(array_unique(array_filter(array_merge([], ...$namespaces))));
    }

    /**
     * Returns all namespaces of the command name.
     *
     * @return string[]
     */
    private function extractAllNamespaces(string $name): array
    {
        // -1 as third argument is needed to skip the command short name when exploding
        $parts = explode(':', $name, -1);
        $namespaces = [];

        foreach ($parts as $part) {
            if (\count($namespaces)) {
                $namespaces[] = end($namespaces).':'.$part;
            } else {
                $namespaces[] = $part;
            }
        }

        return $namespaces;
    }

    /**
     * Returns abbreviated suggestions in string format.
     */
    private function getAbbreviationSuggestions(array $abbrevs): string
    {
        return '    '.implode("\n    ", $abbrevs);
    }
}
