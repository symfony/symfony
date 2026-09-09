<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RemoteEvent\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\RemoteEvent\Messenger\ConsumeRemoteEventHandler;
use Symfony\Component\RemoteEvent\Messenger\ConsumeRemoteEventMessage;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\RemoteEvent\RemoteEventBundle;
use Symfony\Component\RemoteEvent\Tests\Fixtures\TestConsumer;

class RemoteEventBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_remote_event_bundle_test';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->varDir);
    }

    public function testConsumersAreWiredToTheMessengerHandler()
    {
        $kernel = new TestRemoteEventKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $handler = $container->get('test.remote_event.messenger.handler');
        $this->assertInstanceOf(ConsumeRemoteEventHandler::class, $handler);

        $event = new RemoteEvent('name', 'id', ['payload']);
        $handler(new ConsumeRemoteEventMessage('test', $event));

        $this->assertSame([$event], $container->get('test.consumer')->events);
    }

    public function testTheHandlerIsNotRegisteredWhenDisabled()
    {
        $container = new ContainerBuilder();
        new RemoteEventBundle()->getContainerExtension()->load([['enabled' => false]], $container);

        $this->assertFalse($container->hasDefinition('remote_event.messenger.handler'));
    }
}

class TestRemoteEventKernel extends AbstractKernel
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
        yield new RemoteEventBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->services()
            ->set('test.consumer', TestConsumer::class)->autoconfigure()->public()
            ->alias('test.remote_event.messenger.handler', 'remote_event.messenger.handler')->public()
        ;
    }
}
