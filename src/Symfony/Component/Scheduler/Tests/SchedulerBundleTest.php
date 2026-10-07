<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\Scheduler\Command\DebugCommand;
use Symfony\Component\Scheduler\Messenger\SchedulerTransport;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\SchedulerBundle;
use Symfony\Component\Scheduler\Tests\Fixtures\AttributeScheduleProvider;
use Symfony\Component\Scheduler\Tests\Fixtures\AttributeTask;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;

class SchedulerBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_scheduler_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testSchedulesAreExposedAsMessengerTransports()
    {
        $kernel = new TestSchedulerKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->assertInstanceOf(SchedulerTransport::class, $container->get('test.attributes_transport'));
        $this->assertInstanceOf(SchedulerTransport::class, $container->get('test.default_transport'));
        $this->assertInstanceOf(DebugCommand::class, $container->get('test.debug_command'));
        $this->assertInstanceOf(ArrayAdapter::class, $container->get('test.cache'));
    }

    public function testSchedulesAreStatelessByDefault()
    {
        $kernel = new TestSchedulerKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        foreach (['test.default_schedule', 'test.attributes_schedule'] as $id) {
            $schedule = $container->get($id)->getSchedule();

            $this->assertNull($schedule->getState());
            $this->assertNull($schedule->getLock());
            $this->assertFalse($schedule->shouldProcessOnlyLastMissedRun());
        }
    }

    public function testConfiguredScheduleIsStatefulAndLocked()
    {
        $kernel = new TestSchedulerKernel('test', true, $this->varDir, [
            'schedules' => [
                'default' => [
                    'cache_pool' => 'cache.scheduler',
                    'lock_factory' => 'lock.factory',
                    'process_only_last_missed_run' => true,
                ],
                'attributes' => [
                    'cache_pool' => 'cache.scheduler',
                ],
            ],
        ]);
        $kernel->boot();
        $container = $kernel->getContainer();

        $schedule = $container->get('test.default_schedule')->getSchedule();
        $this->assertInstanceOf(Schedule::class, $schedule);
        $this->assertCount(1, $schedule->getRecurringMessages());
        $this->assertSame($container->get('test.cache'), $schedule->getState());
        $this->assertTrue($schedule->shouldProcessOnlyLastMissedRun());

        $lock = $container->get('test.lock_factory')->createLock('scheduler_default');
        $this->assertTrue($lock->acquire());
        $this->assertFalse($schedule->getLock()->acquire());
        $lock->release();

        $schedule = $container->get('test.attributes_schedule')->getSchedule();
        $this->assertSame($container->get('test.cache'), $schedule->getState());
        $this->assertNull($schedule->getLock());
        $this->assertFalse($schedule->shouldProcessOnlyLastMissedRun());
    }

    public function testStatefulScheduleUsesTheSchedulerCachePoolAndTheLockFactory()
    {
        $kernel = new TestSchedulerKernel('test', true, $this->varDir, [
            'schedules' => [
                'default' => ['stateful' => true],
            ],
        ]);
        $kernel->boot();
        $container = $kernel->getContainer();

        $schedule = $container->get('test.default_schedule')->getSchedule();
        $this->assertSame($container->get('test.cache'), $schedule->getState());
        $this->assertFalse($schedule->shouldProcessOnlyLastMissedRun());

        $lock = $container->get('test.lock_factory')->createLock('scheduler_default');
        $this->assertTrue($lock->acquire());
        $this->assertFalse($schedule->getLock()->acquire());
        $lock->release();
    }

    public function testServicesAreDroppedWhenMessengerIsNotAvailable()
    {
        $kernel = new TestSchedulerWithoutMessengerKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->assertFalse($container->has('scheduler.messenger_transport_factory'));
        $this->assertFalse($container->has('scheduler.event_listener'));
        $this->assertFalse($container->has('console.command.scheduler_debug'));
        $this->assertFalse($container->has('cache.scheduler'));
    }

    public function testMessengerIsRequiredWhenASchedulerIsDeclared()
    {
        $kernel = new TestSchedulerWithoutMessengerKernel('other', true, $this->varDir, true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Scheduler support cannot be enabled as the Messenger component is not enabled.');

        $kernel->boot();
    }

    public function testNothingIsRegisteredWhenDisabled()
    {
        $container = new ContainerBuilder();
        new SchedulerBundle()->getContainerExtension()->load([['enabled' => false]], $container);

        $this->assertFalse($container->hasDefinition('scheduler.messenger_transport_factory'));
        $this->assertFalse($container->hasDefinition('serializer.normalizer.scheduler_trigger'));
    }

    public function testServiceCallMessageHandlerRequiresSignature()
    {
        $container = new ContainerBuilder();
        new SchedulerBundle()->getContainerExtension()->load([['enabled' => true]], $container);

        $this->assertSame([['sign' => true]], $container->getDefinition('scheduler.messenger.service_call_message_handler')->getTag('messenger.message_handler'));
    }

    public function testUseMessengerRoutingNotSetKeepsTheParameterNull()
    {
        // no deprecation is expected here: it is only triggered lazily by SchedulerTransport,
        // when a scheduled message is actually redispatched, not on every container build
        $container = new ContainerBuilder();
        new SchedulerBundle()->getContainerExtension()->load([['enabled' => true]], $container);

        $this->assertNull($container->getParameter('.scheduler.use_messenger_routing'));
    }

    public function testUseMessengerRoutingRejectsEnvVar()
    {
        // env placeholders are only recognized as such once resolved against a real container
        // compilation (MergeExtensionConfigurationPass registers them), not through a bare
        // Extension::load() call, so this goes through $container->compile() instead
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        $container->registerExtension(new SchedulerBundle()->getContainerExtension());
        $container->loadFromExtension('scheduler', [
            'use_messenger_routing' => '%env(bool:SCHEDULER_USE_MESSENGER_ROUTING)%',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "scheduler.use_messenger_routing" option is consumed at compile time and cannot use env vars (got "%env(bool:SCHEDULER_USE_MESSENGER_ROUTING)%"). Set a static boolean instead.');

        $container->compile();
    }
}

class TestSchedulerKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir, private array $schedulerConfig = [])
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new SchedulerBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        if ($this->schedulerConfig) {
            $container->extension('scheduler', $this->schedulerConfig);
        }

        $services = $container->services();
        $services
            ->set('lock.factory', LockFactory::class)
                ->args([inline_service(InMemoryStore::class)])
            ->set('messenger.transport_factory', TransportFactory::class)
                ->args([[new Reference('scheduler.messenger_transport_factory')]])
            ->set('messenger.default_serializer', PhpSerializer::class)
            ->set('messenger.receiver_locator', ServiceLocator::class)
                ->args([[]])
            ->set('cache.app', ArrayAdapter::class)
            ->set(AttributeScheduleProvider::class)->autoconfigure()
            ->set(AttributeTask::class)->autoconfigure()
            ->alias('test.attributes_transport', 'messenger.transport.scheduler_attributes')->public()
            ->alias('test.default_transport', 'messenger.transport.scheduler_default')->public()
            ->alias('test.debug_command', 'console.command.scheduler_debug')->public()
            ->alias('test.cache', 'cache.scheduler')->public()
            ->alias('test.lock_factory', 'lock.factory')->public()
            ->alias('test.default_schedule', 'scheduler.provider.default')->public()
            ->alias('test.attributes_schedule', AttributeScheduleProvider::class)->public()
        ;
    }
}

class TestSchedulerWithoutMessengerKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir, private bool $withSchedule = false)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new SchedulerBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        if ($this->withSchedule) {
            $container->services()
                ->set(AttributeScheduleProvider::class)->autoconfigure()
            ;
        }
    }
}
