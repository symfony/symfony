<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class ApplicationDescription
{
    public const GLOBAL_NAMESPACE = '_global';

    private array $namespaces;

    /**
     * @var array<string, Command>
     */
    private array $commands;

    /**
     * @var array<string, Command>
     */
    private array $aliases = [];

    private ?int $nextVerbosity = null;

    /**
     * @param OutputInterface::VERBOSITY_* $verbosity
     */
    public function __construct(
        private Application $application,
        private ?string $namespace = null,
        private bool $showHidden = false,
        private int $verbosity = OutputInterface::VERBOSITY_NORMAL,
    ) {
    }

    public function getNamespaces(): array
    {
        if (!isset($this->namespaces)) {
            $this->inspectApplication();
        }

        return $this->namespaces;
    }

    /**
     * @return Command[]
     */
    public function getCommands(): array
    {
        if (!isset($this->commands)) {
            $this->inspectApplication();
        }

        return $this->commands;
    }

    /**
     * @throws CommandNotFoundException
     */
    public function getCommand(string $name): Command
    {
        if (!isset($this->commands[$name]) && !isset($this->aliases[$name])) {
            throw new CommandNotFoundException(\sprintf('Command "%s" does not exist.', $name));
        }

        return $this->commands[$name] ?? $this->aliases[$name];
    }

    /**
     * Returns the lowest verbosity that would list more commands, or null when none is left out.
     */
    public function getNextVerbosity(): ?int
    {
        if (!isset($this->commands)) {
            $this->inspectApplication();
        }

        return $this->nextVerbosity;
    }

    private function inspectApplication(): void
    {
        $this->commands = [];
        $this->namespaces = [];

        $all = $this->application->all($this->namespace ? $this->application->findNamespace($this->namespace) : null);
        foreach ($this->sortCommands($all) as $namespace => $commands) {
            $names = [];

            foreach ($commands as $name => $command) {
                if (!$command->getName()) {
                    continue;
                }

                if (!$this->showHidden && $command->isHidden()) {
                    continue;
                }

                if (!$this->showHidden && $command->getListedAt() > $this->verbosity) {
                    $this->nextVerbosity = min($this->nextVerbosity ?? \PHP_INT_MAX, $command->getListedAt());

                    continue;
                }

                if ($command->getName() === $name) {
                    $this->commands[$name] = $command;
                } else {
                    $this->aliases[$name] = $command;
                }

                $names[] = $name;
            }

            $this->namespaces[$namespace] = ['id' => $namespace, 'commands' => $names];
        }
    }

    /**
     * @return array<string, array<string, Command>>
     */
    private function sortCommands(array $commands): array
    {
        $namespacedCommands = [];
        $globalCommands = [];
        $sortedCommands = [];
        foreach ($commands as $name => $command) {
            $key = $this->application->extractNamespace($name, 1);
            if (\in_array($key, ['', self::GLOBAL_NAMESPACE], true)) {
                $globalCommands[$name] = $command;
            } else {
                $namespacedCommands[$key][$name] = $command;
            }
        }

        if ($globalCommands) {
            ksort($globalCommands);
            $sortedCommands[self::GLOBAL_NAMESPACE] = $globalCommands;
        }

        if ($namespacedCommands) {
            ksort($namespacedCommands, \SORT_STRING);
            foreach ($namespacedCommands as $key => $commandsSet) {
                ksort($commandsSet);
                $sortedCommands[$key] = $commandsSet;
            }
        }

        return $sortedCommands;
    }
}
