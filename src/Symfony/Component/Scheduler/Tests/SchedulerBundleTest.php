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
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\Scheduler\Command\DebugCommand;
use Symfony\Component\Scheduler\Messenger\SchedulerTransport;
use Symfony\Component\Scheduler\SchedulerBundle;
use Symfony\Component\Scheduler\Tests\Fixtures\AttributeScheduleProvider;
use Symfony\Component\Scheduler\Tests\Fixtures\AttributeTask;

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
}

class TestSchedulerKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir)
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
        $services = $container->services();
        $services
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
