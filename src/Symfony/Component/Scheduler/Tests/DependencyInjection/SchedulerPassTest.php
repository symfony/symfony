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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Kernel as AbstractKernel;
use Symfony\Component\Scheduler\Cron\CronFactory;
use Symfony\Component\Scheduler\Cron\CronRegistry;
use Symfony\Component\Scheduler\DataCollector\SchedulerDataCollector;
use Symfony\Component\Scheduler\DependencyInjection\SchedulerPass;
use Symfony\Component\Scheduler\Scheduler;
use Symfony\Component\Scheduler\SchedulerAwareInterface;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\SchedulerRegistryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Scheduler\Transport\LocalTransport;
use Symfony\Component\Scheduler\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerPassTest extends TestCase
{
    public function testPassCanRegisterSchedulerInDataCollector(): void
    {
        $container = $this->getContainerBuilder();
        $container->register('foo', FooScheduler::class)->addTag('scheduler.hub');

        (new SchedulerPass())->process($container);
        static::assertTrue($container->hasDefinition('debug.scheduler.hub.foo'));
        static::assertTrue($container->getDefinition('scheduler.data_collector')->hasMethodCall('registerScheduler'));
    }

    public function testPassCanRegisterWorkerInDataCollector(): void
    {
        $container = $this->getContainerBuilder();
        $container->register('foo', FooWorker::class)->addTag('scheduler.worker');

        (new SchedulerPass())->process($container);
        static::assertTrue($container->hasDefinition('debug.scheduler.worker.foo'));
        static::assertTrue($container->getDefinition('scheduler.data_collector')->hasMethodCall('registerWorker'));
    }

    public function testPassCannotRegisterKernelSchedulerOnNullKernel(): void
    {
        $container = $this->getContainerBuilder();

        (new SchedulerPass())->process($container);
        static::assertFalse($container->hasDefinition('kernel'));
    }

    public function testEntryPointCannotBeGeneratedWithInvalidEntryPoints(): void
    {
        $container = $this->getContainerBuilder();
        $container->register('scheduler.foo_entry_point', FooEntryPoint::class);
        $container->register('kernel', SchedulerKernel::class);
        $container->register('scheduler.registry', SchedulerRegistryInterface::class);

        (new SchedulerPass())->process($container);
        static::assertTrue($container->hasDefinition('scheduler.foo_entry_point'));
        static::assertFalse($container->getDefinition('scheduler.foo_entry_point')->hasMethodCall('schedule'));
        static::assertFalse($container->getDefinition('kernel')->hasMethodCall('schedule'));
    }

    public function testEntryPointCanBeGeneratedWithValidEntryPoints(): void
    {
        $container = $this->getContainerBuilder();
        $container->register('scheduler.foo_entry_point', FooEntryPoint::class)->addTag('scheduler.entry_point');
        $container->register('scheduler.registry', SchedulerRegistryInterface::class);

        (new SchedulerPass())->process($container);
        static::assertTrue($container->hasDefinition('scheduler.foo_entry_point'));
        static::assertTrue($container->getDefinition('scheduler.foo_entry_point')->hasMethodCall('schedule'));
    }

    public function testPassCanTriggerCronGeneration(): void
    {
        $container = $this->getContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->register('scheduler.hub.foo', SchedulerInterface::class)->addTag('scheduler.hub', [
            'alias' => 'foo',
            'transport' => 'scheduler.transport.foo',
        ]);

        (new SchedulerPass())->process($container);
        static::assertTrue($container->getDefinition('scheduler.cron.factory')->hasMethodCall('create'));
    }

    private function getContainerBuilder(string $schedulerId = 'scheduler'): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->register($schedulerId, Scheduler::class)->addTag('scheduler.hub');
        if ('scheduler' !== $schedulerId) {
            $container->setAlias('scheduler', $schedulerId);
        }
        $container->register('scheduler.data_collector', SchedulerDataCollector::class);
        $container->register('scheduler.cron.registry', CronRegistry::class);
        $container->register('scheduler.cron.factory', CronFactory::class)->setArguments([
            new Reference('scheduler.cron.registry'),
        ]);
        $container->register('scheduler.transport.foo', LocalTransport::class)->setArguments([
            Dsn::fromString('local://root?execution_mode=normal')
        ])->addTag('scheduler.transport', ['alias' => 'foo']);

        return $container;
    }
}

final class FooScheduler implements SchedulerInterface
{
    public function schedule(TaskInterface $task): void
    {
    }

    public function unSchedule(string $taskName): void
    {
    }

    public function update(string $taskName, TaskInterface $task): void
    {
    }

    public function pause(string $taskName): void
    {
    }

    public function resume(string $taskName): void
    {
    }

    public function getDueTasks(): TaskListInterface
    {
        return new TaskList([]);
    }

    public function getTimezone(): \DateTimeZone
    {
        return new \DateTimeZone('UTC');
    }

    public function getTasks(): TaskListInterface
    {
        return new TaskList([]);
    }

    public function reboot(): void
    {
    }
}

final class FooWorker implements WorkerInterface
{
    public function execute(TaskInterface $task): void
    {
        return;
    }

    public function stop(): void
    {
        return;
    }

    public function isRunning(): bool
    {
        return false;
    }

    public function getFailedTasks(): TaskListInterface
    {
        return new TaskList();
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        return;
    }
}

final class Kernel extends AbstractKernel
{
    public function registerBundles()
    {
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }
}

final class SchedulerKernel extends AbstractKernel implements SchedulerAwareInterface
{
    public function registerBundles()
    {
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }

    public function schedule(SchedulerRegistryInterface $registry): void
    {
    }
}

final class FooEntryPoint implements SchedulerAwareInterface
{
    public function schedule(SchedulerRegistryInterface $registry): void
    {
    }
}
