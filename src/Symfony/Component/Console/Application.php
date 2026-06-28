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

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\ArgumentResolver\ArgumentResolverInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\CompleteCommand;
use Symfony\Component\Console\Command\DumpCompletionCommand;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Event\ConsoleAlarmEvent;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Exception\NamespaceNotFoundException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\DebugFormatterHelper;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputAwareInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SignalRegistry\SignalRegistry;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Contracts\Service\ResetInterface;

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
 *     $app->addCommand(new SimpleCommand());
 *     $app->run();
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Application extends AbstractCommand implements ResetInterface
{
    private bool $catchExceptions = true;
    private bool $catchErrors = false;
    private bool $autoExit = true;
    private HelperSet $helperSet;
    private Terminal $terminal;
    private bool $singleCommand = false;
    private bool $initialized = false;

    public function __construct(
        private string $name = 'UNKNOWN',
        private string $version = 'UNKNOWN',
        private ?ContainerInterface $container = null,
    ) {
        parent::__construct();
        $this->terminal = new Terminal();
    }

    /**
     * Runs the current application.
     *
     * @return int 0 if everything went fine, or an error code
     *
     * @throws \Exception When running fails. Bypass this when {@link setCatchExceptions()}.
     */
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        if (\function_exists('putenv')) {
            @putenv('LINES='.$this->terminal->getHeight());
            @putenv('COLUMNS='.$this->terminal->getWidth());
        }

        $input ??= new ArgvInput();
        $output ??= new ConsoleOutput();

        $renderException = function (\Throwable $e) use ($output) {
            if ($output instanceof ConsoleOutputInterface) {
                $this->renderThrowable($e, $output->getErrorOutput());
            } else {
                $this->renderThrowable($e, $output);
            }
        };
        if ($phpHandler = set_exception_handler($renderException)) {
            restore_exception_handler();
            if (!\is_array($phpHandler) || !$phpHandler[0] instanceof ErrorHandler) {
                $errorHandler = true;
            } elseif ($errorHandler = $phpHandler[0]->setExceptionHandler($renderException)) {
                $phpHandler[0]->setExceptionHandler($errorHandler);
            }
        }

        $empty = new \stdClass();
        $prevShellVerbosity = [$_ENV['SHELL_VERBOSITY'] ?? $empty, $_SERVER['SHELL_VERBOSITY'] ?? $empty, getenv('SHELL_VERBOSITY')];

        try {
            $this->configureIO($input, $output);

            $exitCode = $this->doRun($input, $output);
        } catch (\Throwable $e) {
            if ($e instanceof \Exception && !$this->catchExceptions) {
                throw $e;
            }
            if (!$e instanceof \Exception && !$this->catchErrors) {
                throw $e;
            }

            $renderException($e);

            $exitCode = $e->getCode();
            if (is_numeric($exitCode)) {
                $exitCode = (int) $exitCode;
                if ($exitCode <= 0) {
                    $exitCode = 1;
                }
            } else {
                $exitCode = 1;
            }
        } finally {
            // if the exception handler changed, keep it
            // otherwise, unregister $renderException
            if (!$phpHandler) {
                if (set_exception_handler($renderException) === $renderException) {
                    restore_exception_handler();
                }
                restore_exception_handler();
            } elseif (!$errorHandler) {
                $finalHandler = $phpHandler[0]->setExceptionHandler(null);
                if ($finalHandler !== $renderException) {
                    $phpHandler[0]->setExceptionHandler($finalHandler);
                }
            }

            // SHELL_VERBOSITY is set by Application::configureIO so we need to unset/reset it
            // to its previous value to avoid one command verbosity to spread to other commands
            if ($empty === $_ENV['SHELL_VERBOSITY'] = $prevShellVerbosity[0]) {
                unset($_ENV['SHELL_VERBOSITY']);
            }
            if ($empty === $_SERVER['SHELL_VERBOSITY'] = $prevShellVerbosity[1]) {
                unset($_SERVER['SHELL_VERBOSITY']);
            }
            if (\function_exists('putenv')) {
                @putenv('SHELL_VERBOSITY'.(false === ($prevShellVerbosity[2] ?? false) ? '' : '='.$prevShellVerbosity[2]));
            }
        }

        if ($this->autoExit) {
            if ($exitCode > 255) {
                $exitCode = 255;
            }

            exit($exitCode);
        }

        return $exitCode;
    }

    /**
     * Runs the current application.
     *
     * @return int 0 if everything went fine, or an error code
     */
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        if (true === $input->hasParameterOption(['--version', '-V'], true)) {
            $output->writeln($this->getLongVersion());

            return 0;
        }

        return parent::doRun($input, $output);
    }

    public function reset(): void
    {
        if ($this->container?->has('services_resetter')) {
            $this->container->get('services_resetter')->reset();
        }
    }

    public function setHelperSet(HelperSet $helperSet): void
    {
        $this->helperSet = $helperSet;
    }

    /**
     * Get the helper set associated with the command.
     */
    public function getHelperSet(): HelperSet
    {
        return $this->helperSet ??= $this->getDefaultHelperSet();
    }

    public function setDefinition(InputDefinition $definition): void
    {
        $this->definition = $definition;
    }

    /**
     * Gets the InputDefinition related to this Application.
     */
    public function getDefinition(): InputDefinition
    {
        $this->definition ??= $this->getDefaultInputDefinition();

        if ($this->singleCommand) {
            $this->definition->setArguments();
        }

        return $this->definition;
    }

    /**
     * Adds suggestions to $suggestions for the current completion input (e.g. option or argument).
     */
    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if (
            CompletionInput::TYPE_ARGUMENT_VALUE === $input->getCompletionType()
            && 'command' === $input->getCompletionName()
        ) {
            foreach ($this->all() as $name => $command) {
                // skip hidden commands and aliased commands as they already get added below
                if ($command->isHidden() || $command->getName() !== $name) {
                    continue;
                }
                $suggestions->suggestValue(new Suggestion($command->getName(), $command->getDescription()));
                foreach ($command->getAliases() as $name) {
                    $suggestions->suggestValue(new Suggestion($name, $command->getDescription()));
                }
            }

            return;
        }

        if (CompletionInput::TYPE_OPTION_NAME === $input->getCompletionType()) {
            $suggestions->suggestOptions($this->getDefinition()->getOptions());
        }

        if (
            CompletionInput::TYPE_OPTION_VALUE === $input->getCompletionType()
            && ($definition = $this->getDefinition())->hasOption($input->getCompletionName())
        ) {
            $definition->getOption($input->getCompletionName())->complete($input, $suggestions);

            return;
        }
    }

    /**
     * Gets the help message.
     */
    public function getHelp(): string
    {
        return $this->getLongVersion();
    }

    /**
     * Gets whether to catch exceptions or not during commands execution.
     */
    public function areExceptionsCaught(): bool
    {
        return $this->catchExceptions;
    }

    /**
     * Sets whether to catch exceptions or not during commands execution.
     */
    public function setCatchExceptions(bool $boolean): void
    {
        $this->catchExceptions = $boolean;
    }

    /**
     * Sets whether to catch errors or not during commands execution.
     */
    public function setCatchErrors(bool $catchErrors = true): void
    {
        $this->catchErrors = $catchErrors;
    }

    /**
     * Gets whether to automatically exit after a command execution or not.
     */
    public function isAutoExitEnabled(): bool
    {
        return $this->autoExit;
    }

    /**
     * Sets whether to automatically exit after a command execution or not.
     */
    public function setAutoExit(bool $boolean): void
    {
        $this->autoExit = $boolean;
    }

    /**
     * Gets the name of the application.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the application name.
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Gets the application version.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Sets the application version.
     */
    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    /**
     * Returns the long version of the application.
     */
    public function getLongVersion(): string
    {
        if ('UNKNOWN' !== $this->getName()) {
            if ('UNKNOWN' !== $this->getVersion()) {
                $version = \sprintf('%s <info>%s</info>', $this->getName(), $this->getVersion());
            } else {
                $version = $this->getName();
            }
        } else {
            $version = 'Console Tool';
        }

        if ($this->container instanceof SymfonyContainerInterface && $this->container->hasParameter('kernel.environment')) {
            $version .= \sprintf(' (env: <comment>%s</>, debug: <comment>%s</>)', $this->container->getParameter('kernel.environment'), $this->container->getParameter('kernel.debug') ? 'true' : 'false');
        }

        return $version;
    }

    /**
     * Registers a new command.
     */

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

    public function addCommand(callable|Command $command): ?Command
    {
        $this->init();

        return parent::addCommand($command);
    }

    public function get(string $name): Command
    {
        $this->init();
        $command = parent::get($name);

        if ($this->wantHelps) {
            $this->wantHelps = false;

            $helpCommand = $this->get('help');
            $helpCommand->setCommand($command);

            return $helpCommand;
        }

        return $command;

    }

    public function has(string $name): bool
    {
        $this->init();
        return parent::has($name);
    }

    public function find(string $name): Command
    {
        $this->init();

        return parent::find($name);
    }

    public function all(?string $namespace = null): array
    {
        $this->init();
        return parent::all($namespace);
    }

    /**
     * Returns an array of possible abbreviations given a set of names.
     *
     * @return string[][]
     */
    public static function getAbbreviations(array $names): array
    {
        $abbrevs = [];
        foreach ($names as $name) {
            for ($len = \strlen($name); $len > 0; --$len) {
                $abbrev = substr($name, 0, $len);
                $abbrevs[$abbrev][] = $name;
            }
        }

        return $abbrevs;
    }

    public function renderThrowable(\Throwable $e, OutputInterface $output): void
    {
        $output->writeln('', OutputInterface::VERBOSITY_QUIET);

        $this->doRenderThrowable($e, $output);

        if (null !== $this->runningCommand) {
            $output->writeln(\sprintf('<info>%s</info>', OutputFormatter::escape(\sprintf($this->runningCommand->getSynopsis(), $this->getName()))), OutputInterface::VERBOSITY_QUIET);
            $output->writeln('', OutputInterface::VERBOSITY_QUIET);
        }
    }

    protected function doRenderThrowable(\Throwable $e, OutputInterface $output): void
    {
        do {
            $message = trim($e->getMessage());
            if ('' === $message || OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                $class = get_debug_type($e);
                $title = \sprintf('  [%s%s]  ', $class, 0 !== ($code = $e->getCode()) ? ' ('.$code.')' : '');
                $len = Helper::width($title);
            } else {
                $len = 0;
            }

            if (str_contains($message, "@anonymous\0")) {
                $message = preg_replace_callback('/[a-zA-Z_\x7f-\xff][\\\\a-zA-Z0-9_\x7f-\xff]*+@anonymous\x00.*?\.php(?:0x?|:[0-9]++\$)?[0-9a-fA-F]++/', static fn ($m) => class_exists($m[0], false) ? (get_parent_class($m[0]) ?: key(class_implements($m[0])) ?: 'class').'@anonymous' : $m[0], $message);
            }

            $width = $this->terminal->getWidth() ? $this->terminal->getWidth() - 1 : \PHP_INT_MAX;
            $lines = [];
            foreach ('' !== $message ? preg_split('/\r?\n/', $message) : [] as $line) {
                foreach ($this->splitStringByWidth($line, $width - 4) as $line) {
                    // pre-format lines to get the right string length
                    $lineLength = Helper::width($line) + 4;
                    $lines[] = [$line, $lineLength];

                    $len = max($lineLength, $len);
                }
            }

            $messages = [];
            if (!$e instanceof ExceptionInterface || OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                $messages[] = \sprintf('<comment>%s</comment>', OutputFormatter::escape(\sprintf('In %s line %s:', basename($e->getFile()) ?: 'n/a', $e->getLine() ?: 'n/a')));
            }
            $messages[] = $emptyLine = \sprintf('<error>%s</error>', str_repeat(' ', $len));
            if ('' === $message || OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                $messages[] = \sprintf('<error>%s%s</error>', $title, str_repeat(' ', max(0, $len - Helper::width($title))));
            }
            foreach ($lines as $line) {
                $messages[] = \sprintf('<error>  %s  %s</error>', OutputFormatter::escape($line[0]), str_repeat(' ', $len - $line[1]));
            }
            $messages[] = $emptyLine;
            $messages[] = '';

            $output->writeln($messages, OutputInterface::VERBOSITY_QUIET);

            if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                $output->writeln('<comment>Exception trace:</comment>', OutputInterface::VERBOSITY_QUIET);

                // exception related properties
                $trace = $e->getTrace();

                array_unshift($trace, [
                    'function' => '',
                    'file' => $e->getFile() ?: 'n/a',
                    'line' => $e->getLine() ?: 'n/a',
                    'args' => [],
                ]);

                for ($i = 0, $count = \count($trace); $i < $count; ++$i) {
                    $class = $trace[$i]['class'] ?? '';
                    $type = $trace[$i]['type'] ?? '';
                    $function = $trace[$i]['function'] ?? '';
                    $file = $trace[$i]['file'] ?? 'n/a';
                    $line = $trace[$i]['line'] ?? 'n/a';

                    $output->writeln(\sprintf(' %s%s at <info>%s:%s</info>', $class, $function ? $type.$function.'()' : '', $file, $line), OutputInterface::VERBOSITY_QUIET);
                }

                $output->writeln('', OutputInterface::VERBOSITY_QUIET);
            }
        } while ($e = $e->getPrevious());
    }

    /**
     * Configures the input and output instances based on the user arguments and options.
     */
    protected function configureIO(InputInterface $input, OutputInterface $output): void
    {
        if ($input->hasParameterOption(['--ansi'], true)) {
            $output->setDecorated(true);
        } elseif ($input->hasParameterOption(['--no-ansi'], true)) {
            $output->setDecorated(false);
        }

        $shellVerbosity = match (true) {
            $input->hasParameterOption(['--silent'], true) => -2,
            $input->hasParameterOption(['--quiet', '-q'], true) => -1,
            $input->hasParameterOption('-vvv', true) || $input->hasParameterOption('--verbose=3', true) || 3 === $input->getParameterOption('--verbose', false, true) => 3,
            $input->hasParameterOption('-vv', true) || $input->hasParameterOption('--verbose=2', true) || 2 === $input->getParameterOption('--verbose', false, true) => 2,
            $input->hasParameterOption('-v', true) || $input->hasParameterOption('--verbose=1', true) || $input->hasParameterOption('--verbose', true) || $input->getParameterOption('--verbose', false, true) => 1,
            default => (int) ($_ENV['SHELL_VERBOSITY'] ?? $_SERVER['SHELL_VERBOSITY'] ?? getenv('SHELL_VERBOSITY')),
        };

        $output->setVerbosity(match ($shellVerbosity) {
            -2 => OutputInterface::VERBOSITY_SILENT,
            -1 => OutputInterface::VERBOSITY_QUIET,
            1 => OutputInterface::VERBOSITY_VERBOSE,
            2 => OutputInterface::VERBOSITY_VERY_VERBOSE,
            3 => OutputInterface::VERBOSITY_DEBUG,
            default => ($shellVerbosity = 0) ?: $output->getVerbosity(),
        });

        if (0 > $shellVerbosity || $input->hasParameterOption(['--no-interaction', '-n'], true)) {
            $input->setInteractive(false);
        }

        if (\function_exists('putenv')) {
            @putenv('SHELL_VERBOSITY='.$shellVerbosity);
        }
        $_ENV['SHELL_VERBOSITY'] = $shellVerbosity;
        $_SERVER['SHELL_VERBOSITY'] = $shellVerbosity;
    }

    /**
     * Gets the default input definition.
     */
    protected function getDefaultInputDefinition(): InputDefinition
    {
        return new InputDefinition([
            new InputArgument('command', InputArgument::REQUIRED, 'The command to execute'),
            new InputOption('--help', '-h', InputOption::VALUE_NONE, 'Display help for the given command. When no command is given display help for the <info>'.$this->defaultCommand.'</info> command'),
            new InputOption('--silent', null, InputOption::VALUE_NONE, 'Do not output any message'),
            new InputOption('--quiet', '-q', InputOption::VALUE_NONE, 'Only errors are displayed. All other output is suppressed'),
            new InputOption('--verbose', '-v|vv|vvv', InputOption::VALUE_NONE, 'Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug'),
            new InputOption('--version', '-V', InputOption::VALUE_NONE, 'Display this application version'),
            new InputOption('--ansi', '', InputOption::VALUE_NEGATABLE, 'Force (or disable --no-ansi) ANSI output', null),
            new InputOption('--no-interaction', '-n', InputOption::VALUE_NONE, 'Do not ask any interactive question'),
        ]);
    }

    /**
     * Gets the default commands that should always be available.
     *
     * @return Command[]
     */
    protected function getDefaultCommands(): array
    {
        return [new HelpCommand(), new ListCommand(), new CompleteCommand(), new DumpCompletionCommand()];
    }

    /**
     * Gets the default helper set with the helpers that should always be available.
     */
    protected function getDefaultHelperSet(): HelperSet
    {
        return new HelperSet([
            new FormatterHelper(),
            new DebugFormatterHelper(),
            new ProcessHelper(),
            new QuestionHelper(),
        ]);
    }

    /**
     * Returns abbreviated suggestions in string format.
     */
    private function getAbbreviationSuggestions(array $abbrevs): string
    {
        return '    '.implode("\n    ", $abbrevs);
    }

    /**
     * Returns the namespace part of the command name.
     *
     * This method is not part of public API and should not be used directly.
     */
    public function extractNamespace(string $name, ?int $limit = null): string
    {
        $parts = explode(':', $name, -1);

        return implode(':', null === $limit ? $parts : \array_slice($parts, 0, $limit));
    }

    /**
     * Sets the default Command name.
     *
     * @return $this
     */
    public function setDefaultCommand(string $commandName, bool $isSingleCommand = false): static
    {
        $this->defaultCommand = explode('|', ltrim($commandName, '|'))[0];

        if ($isSingleCommand) {
            // Ensure the command exist
            $this->find($commandName);

            $this->singleCommand = true;
        }

        return $this;
    }

    /**
     * @internal
     */
    public function isSingleCommand(): bool
    {
        return $this->singleCommand;
    }

    private function splitStringByWidth(string $string, int $width): array
    {
        // str_split is not suitable for multi-byte characters, we should use preg_split to get char array properly.
        // additionally, array_slice() is not enough as some character has doubled width.
        // we need a function to split string not by character count but by string width
        if (false === $encoding = mb_detect_encoding($string, null, true)) {
            return str_split($string, $width);
        }

        $utf8String = mb_convert_encoding($string, 'utf8', $encoding);
        $lines = [];
        $line = '';

        $offset = 0;
        while (preg_match('/.{1,10000}/u', $utf8String, $m, 0, $offset)) {
            $offset += \strlen($m[0]);

            foreach (preg_split('//u', $m[0]) as $char) {
                // test if $char could be appended to current line
                if (Helper::width($line.$char) <= $width) {
                    $line .= $char;
                    continue;
                }
                // if not, push current line to array and make new line
                $lines[] = str_pad($line, $width);
                $line = $char;
            }
        }

        $lines[] = \count($lines) ? str_pad($line, $width) : $line;

        return mb_convert_encoding($lines, $encoding, 'utf8');
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

    private function init(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;

        foreach ($this->getDefaultCommands() as $command) {
            $this->addCommand($command);
        }

        $this->registerContainerServices();
    }

    private function registerContainerServices(): void
    {
        if (!$this->container) {
            return;
        }

        if ($this->container->has('event_dispatcher')) {
            $this->setDispatcher($this->container->get('event_dispatcher'));
        }
        if ($this->container->has('console.argument_resolver')) {
            $this->setArgumentResolver($this->container->get('console.argument_resolver'));
        }
        if ($this->container->has('console.command_loader')) {
            $this->setCommandLoader($this->container->get('console.command_loader'));
        }

        if (!$commandIds = match (true) {
            !$this->container instanceof SymfonyContainerInterface => $this->container->has('console.command.ids') ? $this->container->get('console.command.ids') : [],
            $this->container->hasParameter('console.command.ids') => $this->container->getParameter('console.command.ids'),
            default => [],
        }) {
            return;
        }

        $lazyCommandIds = match (true) {
            !$this->container instanceof SymfonyContainerInterface => $this->container->has('console.lazy_command.ids') ? $this->container->get('console.lazy_command.ids') : [],
            $this->container->hasParameter('console.lazy_command.ids') => $this->container->getParameter('console.lazy_command.ids'),
            default => [],
        };

        foreach ($commandIds as $id) {
            if (!isset($lazyCommandIds[$id])) {
                $this->addCommand($this->container->get($id));
            }
        }
    }

    /**
     * Gets the name of the command based on input.
     */
    protected function getCommandName(InputInterface $input): ?string
    {
        return $this->singleCommand ? $this->defaultCommand : $input->getFirstArgument();
    }

    public function getApplication(): ?Application
    {
        return $this;
    }
}
