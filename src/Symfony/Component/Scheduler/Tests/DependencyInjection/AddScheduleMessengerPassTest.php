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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Scheduler\DependencyInjection\AddScheduleMessengerPass;

class AddScheduleMessengerPassTest extends TestCase
{
    #[DataProvider('processSchedulerTaskCommandProvider')]
    public function testProcessSchedulerTaskCommand(array $arguments, string $expectedCommand)
    {
        $container = new ContainerBuilder();

        $definition = new Definition(SchedulableCommand::class);
        $definition->addTag('console.command');
        $definition->addTag('scheduler.task', $arguments);
        $container->setDefinition(SchedulableCommand::class, $definition);

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
    }
}

#[AsCommand(name: 'schedulable')]
class SchedulableCommand
{
    public function __invoke(): void
    {
    }
}
