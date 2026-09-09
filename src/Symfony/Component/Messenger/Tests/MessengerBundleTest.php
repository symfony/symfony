<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\MessengerBundle;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class MessengerBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_messenger_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testTheBusRoutesMessagesToTheirTransport()
    {
        $kernel = new TestMessengerKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $bus = $container->get('test.messenger.default_bus');
        $this->assertInstanceOf(MessageBusInterface::class, $bus);

        $transport = $container->get('test.messenger.transport.async');
        $this->assertInstanceOf(InMemoryTransport::class, $transport);

        $bus->dispatch(new DummyMessage('hello'));
        $this->assertCount(1, $transport->getSent());
    }

    public function testTheServicesNeedingAnotherBundleAreDropped()
    {
        $kernel = new TestMessengerKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        // these need a lock factory, a cache pool, a stopwatch and a profiler, which only other bundles register
        $this->assertFalse($container->has('messenger.middleware.deduplicate_middleware'));
        $this->assertFalse($container->has('messenger.listener.stop_worker_on_restart_signal_listener'));
        $this->assertFalse($container->has('messenger.middleware.traceable'));
        $this->assertFalse($container->has('data_collector.messenger'));
    }
}

class TestMessengerKernel extends AbstractKernel
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
        yield new MessengerBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('messenger', [
            'transports' => ['async' => 'in-memory://'],
            'routing' => [DummyMessage::class => 'async'],
        ]);
        $container->services()
            ->alias('test.messenger.default_bus', 'messenger.default_bus')->public()
            ->alias('test.messenger.transport.async', 'messenger.transport.async')->public()
        ;
    }
}
