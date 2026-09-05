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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * The commands resolved for the current invocation, from the root to the leaf,
 * together with the input bound at each level.
 *
 * Implicit tree levels have no registered command and do not appear, except
 * when the invocation ends on one: the running command is always the last level.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class CommandChain
{
    /**
     * @param list<array{Command, InputInterface}> $levels
     */
    public function __construct(
        private array $levels,
    ) {
    }

    /**
     * @return list<Command>
     */
    public function getCommands(): array
    {
        return array_column($this->levels, 0);
    }

    /**
     * @return list<InputInterface>
     */
    public function getInputs(): array
    {
        return array_column($this->levels, 1);
    }

    /**
     * Returns the input bound at the level named by a command name, or by the
     * class of the command or of its invokable code.
     */
    public function getInput(string $commandNameOrClass): ?InputInterface
    {
        return $this->getLevel($commandNameOrClass)[1] ?? null;
    }

    /**
     * Returns the command of the level named by a command name, or by the class
     * of the command or of its invokable code.
     */
    public function getCommand(string $commandNameOrClass): ?Command
    {
        return $this->getLevel($commandNameOrClass)[0] ?? null;
    }

    /**
     * @return array{Command, InputInterface}|null
     */
    private function getLevel(string $commandNameOrClass): ?array
    {
        foreach (array_reverse($this->levels) as $level) {
            if ($level[0]->getName() === $commandNameOrClass || $level[0] instanceof $commandNameOrClass || self::getInvokable($level[0]) instanceof $commandNameOrClass) {
                return $level;
            }
        }

        return null;
    }

    /**
     * Returns the object behind the code of a command, which a container wraps in a closure.
     */
    private static function getInvokable(Command $command): ?object
    {
        $code = $command->getCode();

        if ($code instanceof \Closure) {
            $code = new \ReflectionFunction($code)->getClosureThis();
        } elseif (\is_array($code)) {
            $code = $code[0];
        }

        return \is_object($code) ? $code : null;
    }
}
