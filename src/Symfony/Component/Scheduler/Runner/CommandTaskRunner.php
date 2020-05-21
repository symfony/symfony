<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Runner;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Exception\UnrecognizedCommandException;
use Symfony\Component\Scheduler\Task\CommandTask;
use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CommandTaskRunner implements RunnerInterface
{
    private $application;
    private $statusCode;

    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        if (!$this->support($task)) {
            throw new InvalidArgumentException(sprintf('The task must be an instance of %s, given %s', CommandTask::class, get_class($task)));
        }

        $input = $this->buildInput($task);
        $output = new BufferedOutput();

        $this->application->setCatchExceptions(false);
        $this->application->setAutoExit(false);

        try {
            $this->statusCode = $this->application->run($input, $output);
        } catch (\Throwable $throwable) {
            return Output::forError($task, $this->statusCode, $output->fetch());
        }

        return Output::forSuccess($task, $this->statusCode, $output->fetch());
    }

    private function buildInput(TaskInterface $task): InputInterface
    {
        $command = $this->findCommand($task->getCommand());
        $options = $this->buildOptions($task);

        $arguments = implode(' ', $task->get('command_arguments'));
        $options = implode(' ', $options);

        return new StringInput(sprintf('%s %s %s', $command->getName(), $arguments, $options));
    }

    private function buildOptions(TaskInterface $task): array
    {
        $arguments = [];

        foreach ($task->get('command_options') as $key => $argument) {
            $arguments[] = sprintf('%s="%s"', $key, $argument);
        }

        return $arguments;
    }

    private function findCommand(string $command): Command
    {
        $registeredCommands = $this->application->all();

        if (\array_key_exists($command, $registeredCommands)) {
            return $registeredCommands[$command];
        }

        foreach ($registeredCommands as $registeredCommand) {
            if ($command === \get_class($registeredCommand)) {
                return $registeredCommand;
            }
        }

        throw new UnrecognizedCommandException(sprintf('The given command "%s" cannot be found!', $command));
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof CommandTask;
    }
}
