<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Runner;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Scheduler\Exception\UnrecognizedCommandException;
use Symfony\Component\Scheduler\Runner\CommandTaskRunner;
use Symfony\Component\Scheduler\Task\CommandTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CommandTaskRunnerTest extends TestCase
{
    public function testCommandCannotBeCalledWithoutBeingRegistered(): void
    {
        $application = new Application();
        $task = new CommandTask('foo', 'app:foo');

        $runner = new CommandTaskRunner($application);
        static::assertTrue($runner->support($task));
        static::expectException(UnrecognizedCommandException::class);
        static::expectExceptionMessage('The given command "app:foo" cannot be found!');
        static::assertNull($runner->run($task));

        $task = new CommandTask('foo', FooCommand::class);
        static::expectException(UnrecognizedCommandException::class);
        static::expectExceptionMessage('The given command "app:foo" cannot be found!');
        static::assertNull($runner->run($task)->getOutput());
    }

    public function testCommandCanBeCalledWhenRegistered(): void
    {
        $application = new Application();
        $application->add(new FooCommand());

        $task = new CommandTask('foo', 'app:foo');

        $runner = new CommandTaskRunner($application);
        $output = $runner->run($task);

        static::assertSame(0, $output->getExitCode());
    }

    public function testCommandCanBeCalledWithOptions(): void
    {
        $application = new Application();
        $application->add(new FooCommand());

        $task = new CommandTask('foo', 'app:foo', [
            'command_options' => ['--env' => 'test'],
        ]);

        $runner = new CommandTaskRunner($application);
        $output = $runner->run($task);

        static::assertSame(0, $output->getExitCode());
        static::assertStringContainsString('This command is executed in "test" env', $output->getOutput());
    }

    public function testCommandCanBeCalledWithArgument(): void
    {
        $application = new Application();
        $application->add(new BarCommand());

        $task = new CommandTask('foo', 'app:bar', [
            'command_arguments' => ['name' => 'bar'],
            'command_options' => ['--env' => 'test'],
        ]);

        $runner = new CommandTaskRunner($application);
        $output = $runner->run($task);

        static::assertSame(0, $output->getExitCode());
        static::assertStringContainsString('This command has the "bar" name', $output->getOutput());
    }
}

final class FooCommand extends Command
{
    protected static $defaultName = 'app:foo';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->hasOption('env')) {
            $output->write(sprintf('This command is executed in "%s" env', $input->getOption('env')));

            return 0;
        }

        return 0;
    }
}

final class BarCommand extends Command
{
    protected static $defaultName = 'app:bar';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED)
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->hasArgument('name')) {
            $output->write(sprintf('This command has the "%s" name', $input->getArgument('name')));

            return 0;
        }

        return 0;
    }
}
