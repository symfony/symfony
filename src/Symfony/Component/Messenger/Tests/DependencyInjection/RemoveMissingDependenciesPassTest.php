<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\RemoveMissingDependenciesPass as ContainerRemoveMissingDependenciesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\Messenger\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Messenger\MessengerBundle;

class RemoveMissingDependenciesPassTest extends TestCase
{
    public function testTheDeduplicationServicesAreRemovedWithoutALockFactory()
    {
        $container = $this->process();

        $this->assertFalse($container->hasDefinition('messenger.middleware.deduplicate_middleware'));
        $this->assertFalse($container->hasDefinition('messenger.failure.release_deduplication_lock_on_failure_listener'));
        $this->assertNotContains(['id' => 'deduplicate_middleware'], $container->getParameter('messenger.bus.default.middleware'));
    }

    public function testTheDeduplicationServicesAreKeptWithALockFactory()
    {
        $container = $this->process(static function (ContainerBuilder $container) {
            $container->register('my_lock_factory');
            $container->setAlias('lock.factory', 'my_lock_factory');
        });

        $this->assertTrue($container->hasDefinition('messenger.middleware.deduplicate_middleware'));
        $this->assertTrue($container->hasDefinition('messenger.failure.release_deduplication_lock_on_failure_listener'));
        $this->assertContains(['id' => 'deduplicate_middleware'], $container->getParameter('messenger.bus.default.middleware'));
    }

    public function testTheTraceableMiddlewareIsRemovedWithoutAStopwatch()
    {
        $container = $this->process(debug: true);

        $this->assertFalse($container->hasDefinition('messenger.middleware.traceable'));
        $this->assertNotContains('traceable', array_column($container->getParameter('messenger.bus.default.middleware'), 'id'));
    }

    public function testTheTraceableMiddlewareIsKeptWithAStopwatch()
    {
        $container = $this->process(static function (ContainerBuilder $container) {
            $container->register('debug.stopwatch', \stdClass::class);
        }, debug: true);

        $this->assertTrue($container->hasDefinition('messenger.middleware.traceable'));
        $this->assertContains('traceable', array_column($container->getParameter('messenger.bus.default.middleware'), 'id'));
    }

    public function testTheDataCollectorIsRemovedWithoutAProfiler()
    {
        $this->assertFalse($this->process(debug: true)->hasDefinition('data_collector.messenger'));

        $container = $this->process(static function (ContainerBuilder $container) {
            $container->register('profiler', \stdClass::class);
        }, debug: true);

        $this->assertTrue($container->hasDefinition('data_collector.messenger'));
    }

    public function testTheWorkerRestartServicesAreRemovedWithoutACachePool()
    {
        $container = $this->process();

        $this->assertFalse($container->hasDefinition('messenger.listener.stop_worker_on_restart_signal_listener'));
        $this->assertFalse($container->hasDefinition('console.command.messenger_stop_workers'));

        $container = $this->process(static function (ContainerBuilder $container) {
            $container->register('cache.app', \stdClass::class);
            $container->register('cache.messenger.restart_workers_signal', \stdClass::class);
        });

        $this->assertTrue($container->hasDefinition('messenger.listener.stop_worker_on_restart_signal_listener'));
        $this->assertTrue($container->hasDefinition('console.command.messenger_stop_workers'));
    }

    public function testTheServicesResetListenerIsRemovedWithoutTheConsumeCommand()
    {
        $container = $this->process();
        $this->assertTrue($container->hasDefinition('messenger.listener.reset_services'));

        $container = $this->process(static function (ContainerBuilder $container) {
            $container->removeDefinition('console.command.messenger_consume_messages');
        });

        $this->assertFalse($container->hasDefinition('messenger.listener.reset_services'));
    }

    private function process(?\Closure $configure = null, bool $debug = false): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag([
            'kernel.debug' => $debug,
            'kernel.project_dir' => __DIR__,
            'kernel.secret' => 's3cr3t',
        ]));
        $container->registerExtension(new MessengerBundle()->getContainerExtension());
        new PhpFileLoader($container, new FileLocator(__DIR__.'/Fixtures'))->load('messenger.php');

        $configurePass = new class($configure) implements CompilerPassInterface {
            public function __construct(private ?\Closure $configure)
            {
            }

            public function process(ContainerBuilder $container): void
            {
                $this->configure?->__invoke($container);
            }
        };

        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([$configurePass, new ContainerRemoveMissingDependenciesPass(), new RemoveMissingDependenciesPass()]);
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

        return $container;
    }
}
