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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Messenger\Stamp\StampInterface;
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

    public function testTaskIsScheduledWhenEnvironmentMatches()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $definition = new Definition(SchedulableCommand::class);
        $definition->addTag('console.command');
        $definition->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour', 'env' => ['prod', 'staging']]);
        $container->setDefinition(SchedulableCommand::class, $definition);

        (new AddScheduleMessengerPass())->process($container);

        $this->assertTrue($container->hasDefinition('scheduler.provider.default'));
        $this->assertCount(1, $container->getDefinition('scheduler.provider.default')->getMethodCalls());
    }

    public function testTaskIsScheduledWhenEnvironmentMatchesWithStringEnv()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $definition = new Definition(SchedulableCommand::class);
        $definition->addTag('console.command');
        $definition->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour', 'env' => 'prod']);
        $container->setDefinition(SchedulableCommand::class, $definition);

        (new AddScheduleMessengerPass())->process($container);

        $this->assertTrue($container->hasDefinition('scheduler.provider.default'));
    }

    public function testTaskIsNotScheduledWhenEnvironmentDoesNotMatch()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'dev');

        $definition = new Definition(SchedulableCommand::class);
        $definition->addTag('console.command');
        $definition->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour', 'env' => ['prod', 'staging']]);
        $container->setDefinition(SchedulableCommand::class, $definition);

        (new AddScheduleMessengerPass())->process($container);

        $this->assertSame([], $container->getDefinition('scheduler.provider.default')->getMethodCalls());
    }

    public function testScheduleAndItsReceiverSurviveWhenEveryTaskIsFilteredOut()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'dev');

        $definition = new Definition(SchedulableCommand::class);
        $definition->addTag('console.command');
        $definition->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour', 'schedule' => 'nightly', 'env' => 'prod']);
        $container->setDefinition(SchedulableCommand::class, $definition);

        (new AddScheduleMessengerPass())->process($container);

        // the worker command must keep working in every environment
        $this->assertTrue($container->hasDefinition('messenger.transport.scheduler_nightly'));
        $this->assertSame([], $container->getDefinition('scheduler.provider.nightly')->getMethodCalls());
    }

    public function testUnknownEnvironmentIsReported()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'dev');
        $container->setParameter('.container.known_envs', ['dev', 'test', 'prod']);

        $definition = new Definition(SchedulableCommand::class);
        $definition->addTag('console.command');
        $definition->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour', 'env' => 'porduction']);
        $container->setDefinition(SchedulableCommand::class, $definition);

        (new AddScheduleMessengerPass())->process($container);

        $this->assertStringContainsString('is restricted to environment(s) "porduction"', implode("\n", $container->getCompiler()->getLog()));
    }

    public function testUnknownEnvironmentIsNotReportedWhenNoEnvironmentIsKnown()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'dev');

        $definition = new Definition(SchedulableCommand::class);
        $definition->addTag('console.command');
        $definition->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour', 'env' => 'staging']);
        $container->setDefinition(SchedulableCommand::class, $definition);

        (new AddScheduleMessengerPass())->process($container);

        $this->assertSame([], $container->getCompiler()->getLog());
    }

    public function testTaskWithEmptyEnvIsScheduledInAnyEnvironment()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'dev');

        $definition = new Definition(SchedulableCommand::class);
        $definition->addTag('console.command');
        $definition->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour', 'env' => []]);
        $container->setDefinition(SchedulableCommand::class, $definition);

        (new AddScheduleMessengerPass())->process($container);

        $this->assertTrue($container->hasDefinition('scheduler.provider.default'));
    }

    public function testTaskWithEnvIsScheduledWhenKernelEnvironmentIsUnknown()
    {
        $container = new ContainerBuilder();

        $definition = new Definition(SchedulableCommand::class);
        $definition->addTag('console.command');
        $definition->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour', 'env' => 'prod']);
        $container->setDefinition(SchedulableCommand::class, $definition);

        (new AddScheduleMessengerPass())->process($container);

        $this->assertTrue($container->hasDefinition('scheduler.provider.default'));
    }

    public function testSchedulerServicesAreDroppedWhenMessengerIsMissing()
    {
        $container = new ContainerBuilder();
        $container->register('scheduler.messenger_transport_factory');
        $container->register('scheduler.event_listener');
        $container->register('console.command.scheduler_debug');
        $container->register('cache.scheduler');

        (new AddScheduleMessengerPass())->process($container);

        $this->assertFalse($container->hasDefinition('scheduler.messenger_transport_factory'));
        $this->assertFalse($container->hasDefinition('scheduler.event_listener'));
        $this->assertFalse($container->hasDefinition('console.command.scheduler_debug'));
        $this->assertFalse($container->hasDefinition('cache.scheduler'));
    }

    public function testTheCachePoolIsDroppedWithoutAnApplicationPool()
    {
        $container = new ContainerBuilder();
        $container->register('scheduler.messenger_transport_factory');
        $container->register('messenger.transport_factory');
        $container->register('cache.scheduler');

        (new AddScheduleMessengerPass())->process($container);

        $this->assertFalse($container->hasDefinition('cache.scheduler'));

        $container = new ContainerBuilder();
        $container->register('scheduler.messenger_transport_factory');
        $container->register('messenger.transport_factory');
        $container->register('cache.app');
        $container->register('cache.scheduler');

        (new AddScheduleMessengerPass())->process($container);

        $this->assertTrue($container->hasDefinition('cache.scheduler'));
    }

    public function testStatefulScheduleIsNotLockedWithoutALockFactory()
    {
        $container = new ContainerBuilder();
        $container->register('scheduler.messenger_transport_factory');
        $container->register('messenger.transport_factory');
        $container->register('cache.app');
        $container->register('cache.scheduler');
        $container->register('app.task', \stdClass::class)
            ->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour']);
        $container->setParameter('.scheduler.schedules', [
            'default' => ['stateful' => true, 'cache_pool' => null, 'lock_factory' => null, 'process_only_last_missed_run' => false],
        ]);

        (new AddScheduleMessengerPass())->process($container);

        $calls = $container->getDefinition('scheduler.provider.default')->getMethodCalls();

        $this->assertSame(['add', 'stateful'], array_column($calls, 0));
        $this->assertEquals([new Reference('cache.scheduler')], $calls[1][1]);
    }

    public function testConfiguringAnUnknownScheduleIsReported()
    {
        $container = new ContainerBuilder();
        $container->register('scheduler.messenger_transport_factory');
        $container->register('messenger.transport_factory');
        $container->register('app.task', \stdClass::class)
            ->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour']);
        $container->setParameter('.scheduler.schedules', [
            'unknown' => ['stateful' => true, 'cache_pool' => null, 'lock_factory' => null, 'process_only_last_missed_run' => false],
        ]);

        (new AddScheduleMessengerPass())->process($container);

        $this->assertStringContainsString('Schedule "unknown" is configured but no task or schedule provider uses it; check for a typo (known schedules: "default").', implode("\n", $container->getCompiler()->getLog()));
        $this->assertFalse($container->hasDefinition('scheduler.provider.unknown'));
        $this->assertFalse($container->hasDefinition('messenger.transport.scheduler_unknown'));
    }

    public function testMissingMessengerIsReportedWhenATaskIsDeclared()
    {
        $container = new ContainerBuilder();
        $container->register('scheduler.messenger_transport_factory');
        $container->register(SchedulableCommand::class, SchedulableCommand::class)
            ->addTag('console.command')
            ->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Scheduler support cannot be enabled as the Messenger component is not enabled.');

        (new AddScheduleMessengerPass())->process($container);
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

    public function testStampsWrapTheMessageInAnEnvelope()
    {
        $message = self::messageOfTaskWithTags(['stamps' => [SomeStamp::class]]);

        $this->assertSame(Envelope::class, $message->getClass());
        $this->assertSame(RunCommandMessage::class, $message->getArgument(0)->getClass());
        $this->assertSame([SomeStamp::class], array_map(static fn (Definition $stamp) => $stamp->getClass(), $message->getArgument(1)));
    }

    public function testStampsWrapAServiceCallMessageToo()
    {
        $message = self::messageOfTaskWithTags(['stamps' => [SomeStamp::class]], []);

        $this->assertSame(Envelope::class, $message->getClass());
        $this->assertSame(ServiceCallMessage::class, $message->getArgument(0)->getClass());
    }

    public function testStampsCanBeGivenAsASingleClassName()
    {
        $stamps = self::messageOfTaskWithTags(['stamps' => SomeStamp::class])->getArgument(1);

        $this->assertCount(1, $stamps);
        $this->assertSame(SomeStamp::class, $stamps[0]->getClass());
    }

    public function testStampCanCarryConstructorArguments()
    {
        $stamps = self::messageOfTaskWithTags(['stamps' => [SomeStamp::class, StampWithArguments::class => ['batch', 5]]])->getArgument(1);

        $this->assertSame([], $stamps[0]->getArguments());
        $this->assertSame(StampWithArguments::class, $stamps[1]->getClass());
        $this->assertSame(['batch', 5], $stamps[1]->getArguments());
    }

    public function testStampsRideInsideTheRedispatchMessageWhenTransportsAreSet()
    {
        $message = self::messageOfTaskWithTags(['stamps' => [SomeStamp::class], 'transports' => 'async']);

        $this->assertSame(RedispatchMessage::class, $message->getClass());
        $this->assertSame(Envelope::class, $message->getArgument(0)->getClass());
        $this->assertSame('async', $message->getArgument(1));
    }

    public function testTaskWithoutStampsKeepsItsMessageUnwrapped()
    {
        $this->assertSame(RunCommandMessage::class, self::messageOfTaskWithTags(['stamps' => []])->getClass());
    }

    public function testStampInstanceIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('must list stamp classes, not instances: pass "%s::class" instead', SomeStamp::class));

        self::messageOfTaskWithTags(['stamps' => [new SomeStamp()]]);
    }

    public function testClassThatDoesNotImplementStampInterfaceIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('must list instantiable classes implementing "%s", got "%s".', StampInterface::class, NotAStamp::class));

        self::messageOfTaskWithTags(['stamps' => [NotAStamp::class]]);
    }

    public function testStampThatCannotBeInstantiatedIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('must list instantiable classes implementing "%s", got "%s".', StampInterface::class, AbstractStamp::class));

        self::messageOfTaskWithTags(['stamps' => [AbstractStamp::class]]);
    }

    /**
     * @param array<string, mixed> $taskAttributes
     * @param string[]             $tags
     */
    private static function messageOfTaskWithTags(array $taskAttributes, array $tags = ['console.command']): Definition
    {
        $container = new ContainerBuilder();

        $definition = new Definition(SchedulableCommand::class);

        foreach ($tags as $tag) {
            $definition->addTag($tag);
        }

        $definition->addTag('scheduler.task', ['trigger' => 'every', 'frequency' => '1 hour'] + $taskAttributes);
        $container->setDefinition('some.task', $definition);

        (new AddScheduleMessengerPass())->process($container);

        return $container->getDefinition('scheduler.provider.default')->getMethodCalls()[0][1][0]->getArgument('$message');
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

class SomeStamp implements StampInterface
{
}

class StampWithArguments implements StampInterface
{
    public function __construct(
        public readonly string $group,
        public readonly int $size,
    ) {
    }
}

class NotAStamp
{
}

abstract class AbstractStamp implements StampInterface
{
}
