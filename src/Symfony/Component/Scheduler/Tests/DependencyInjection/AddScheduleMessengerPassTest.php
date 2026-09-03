<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Scheduler\DependencyInjection\AddScheduleMessengerPass;
use Symfony\Component\Scheduler\Messenger\ServiceCallMessage;

class AddScheduleMessengerPassTest extends TestCase
{
    #[DataProvider('processSchedulerTaskCommandProvider')]
    public function testProcessSchedulerTaskCommand(array $arguments, string $expectedCommand, string $commandClass = SchedulableCommand::class)
    {
        $container = new ContainerBuilder();

        $definition = new Definition($commandClass);
        $definition->addTag('console.command');
        $definition->addTag('scheduler.task', $arguments);
        $container->setDefinition($commandClass, $definition);

        (new AddScheduleMessengerPass())->process($container);

        $schedulerProvider = $container->getDefinition('scheduler.provider.default');
        $calls = $schedulerProvider->getMethodCalls();

        $this->assertCount(1, $calls);
        $this->assertCount(2, $calls[0]);

        $messageDefinition = $calls[0][1][0];
        $messageArguments = $messageDefinition->getArgument('$message');
        $command = $messageArguments->getArgument(0);

        $this->assertSame($expectedCommand, $command);
    }

    #[DataProvider('processSchedulerTaskCommandNameFromTagProvider')]
    public function testProcessSchedulerTaskCommandNameFromTag(array $commandTagAttributes, string $expectedCommand)
    {
        $container = new ContainerBuilder();

        $definition = new Definition(SchedulableCommand::class);
        $definition->addTag('console.command', $commandTagAttributes);
        $definition->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour']);
        $container->setDefinition(SchedulableCommand::class, $definition);

        (new AddScheduleMessengerPass())->process($container);

        $schedulerProvider = $container->getDefinition('scheduler.provider.default');
        $calls = $schedulerProvider->getMethodCalls();

        $messageDefinition = $calls[0][1][0];
        $messageArguments = $messageDefinition->getArgument('$message');
        $command = $messageArguments->getArgument(0);

        $this->assertSame($expectedCommand, $command);
    }

    public function testProcessSchedulerTaskCommandWithMultipleCommandMethods()
    {
        $container = new ContainerBuilder();

        $definition = new Definition(MultiCommandSchedulableCommand::class);
        $definition->addTag('console.command', ['command' => 'schedulable:one', 'method' => 'command1']);
        $definition->addTag('console.command', ['command' => 'schedulable:two', 'method' => 'command2']);
        $definition->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour', 'method' => 'command1']);
        $definition->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour', 'method' => 'command2']);
        $container->setDefinition(MultiCommandSchedulableCommand::class, $definition);

        (new AddScheduleMessengerPass())->process($container);

        $schedulerProvider = $container->getDefinition('scheduler.provider.default');
        $tasks = $schedulerProvider->getMethodCalls()[0][1];

        $this->assertSame('schedulable:one', $tasks[0]->getArgument('$message')->getArgument(0));
        $this->assertSame('schedulable:two', $tasks[1]->getArgument('$message')->getArgument(0));
    }

    public function testProcessSchedulerTaskCommandWithMixedCommandAndPlainMethods()
    {
        $container = new ContainerBuilder();

        $definition = new Definition(MixedCommandAndPlainSchedulableCommand::class);
        $definition->addTag('console.command', ['command' => 'schedulable:command', 'method' => 'command1']);
        $definition->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour', 'method' => 'command1']);
        $definition->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour', 'method' => 'plainTask']);
        $container->setDefinition(MixedCommandAndPlainSchedulableCommand::class, $definition);

        (new AddScheduleMessengerPass())->process($container);

        $schedulerProvider = $container->getDefinition('scheduler.provider.default');
        $tasks = $schedulerProvider->getMethodCalls()[0][1];

        $this->assertSame(RunCommandMessage::class, $tasks[0]->getArgument('$message')->getClass());
        $this->assertSame(ServiceCallMessage::class, $tasks[1]->getArgument('$message')->getClass());
        $this->assertSame('plainTask', $tasks[1]->getArgument('$message')->getArgument(1));
    }

    public function testProcessSchedulerTaskCommandWithClassLevelCommandAndTaskMethod()
    {
        $container = new ContainerBuilder();

        $definition = new Definition(SchedulableCommand::class);
        $definition->addTag('console.command');
        $definition->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour', 'method' => 'someMethod']);
        $container->setDefinition(SchedulableCommand::class, $definition);

        (new AddScheduleMessengerPass())->process($container);

        $schedulerProvider = $container->getDefinition('scheduler.provider.default');
        $tasks = $schedulerProvider->getMethodCalls()[0][1];

        $this->assertSame(RunCommandMessage::class, $tasks[0]->getArgument('$message')->getClass());
        $this->assertSame('schedulable', $tasks[0]->getArgument('$message')->getArgument(0));
    }

    public static function processSchedulerTaskCommandNameFromTagProvider(): iterable
    {
        yield 'tag command attribute overrides attribute name' => [['command' => 'custom-name'], 'custom-name'];
        yield 'tag command attribute with aliases' => [['command' => 'custom-name|alias1|alias2'], 'custom-name'];
        yield 'tag command attribute with hidden leading pipe' => [['command' => '|real-name'], 'real-name'];
    }

    public static function processSchedulerTaskCommandProvider(): iterable
    {
        yield 'no arguments' => [['trigger' => 'every', 'frequency' => '1 hour'], 'schedulable'];
        yield 'null arguments' => [['trigger' => 'every', 'frequency' => '1 hour', 'arguments' => null], 'schedulable'];
        yield 'empty arguments' => [['trigger' => 'every', 'frequency' => '1 hour', 'arguments' => ''], 'schedulable'];
        yield 'test argument' => [['trigger' => 'every', 'frequency' => '1 hour', 'arguments' => 'test'], 'schedulable test'];
        yield 'array arguments' => [['trigger' => 'every', 'frequency' => '1 hour', 'arguments' => ['arg1', 'arg2']], 'schedulable arg1 arg2'];
        yield 'array arguments with spaces' => [['trigger' => 'every', 'frequency' => '1 hour', 'arguments' => ['hello world', 'foo']], 'schedulable '.escapeshellarg('hello world').' foo'];
        yield 'empty array arguments' => [['trigger' => 'every', 'frequency' => '1 hour', 'arguments' => []], 'schedulable'];
        yield 'array options' => [['trigger' => 'every', 'frequency' => '1 hour', 'arguments' => ['--option1' => 'first', '--option2' => true, '--option3' => false]], 'schedulable --option1=first --option2=1 --option3'];
        yield 'array arguments and options' => [['trigger' => 'every', 'frequency' => '1 hour', 'arguments' => ['arg1' => 'first_one', 'arg2' => 'second_one', '--option1' => 'first', '--option2' => true, '--option3' => false]], 'schedulable first_one second_one --option1=first --option2=1 --option3'];
        yield 'alias, no arguments' => [['trigger' => 'every', 'frequency' => '1 hour'], 'schedulable-with-alias', SchedulableCommandWithAlias::class];
        yield 'alias, with argument' => [['trigger' => 'every', 'frequency' => '1 hour', 'arguments' => 'test'], 'schedulable-with-alias test', SchedulableCommandWithAlias::class];
    }
}

#[AsCommand(name: 'schedulable')]
class SchedulableCommand
{
    public function __invoke(): void
    {
    }
}

#[AsCommand(name: 'schedulable-with-alias', aliases: ['schedulable-alias'])]
class SchedulableCommandWithAlias
{
    public function __invoke(): void
    {
    }
}

class MultiCommandSchedulableCommand
{
    public function command1(): void
    {
    }

    public function command2(): void
    {
    }
}

class MixedCommandAndPlainSchedulableCommand
{
    public function command1(): void
    {
    }

    public function plainTask(): void
    {
    }
}
