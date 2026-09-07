<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockBundle;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\SemaphoreStore;

class LockBundleExtensionTest extends TestCase
{
    public function testDefaultLock()
    {
        $container = $this->createContainerFromFile('lock');

        $this->assertTrue($container->hasDefinition('lock.default.factory'));
        $storeId = (string) $container->getDefinition('lock.default.factory')->getArgument(0);
        $storeDef = $container->getDefinition($storeId);

        if (SemaphoreStore::isSupported()) {
            $this->assertSame('.lock.semaphore.store', $storeId);
            $this->assertSame(SemaphoreStore::class, $storeDef->getClass());
            $this->assertSame('%kernel.project_dir%', $storeDef->getArgument(0));
            $this->assertTrue($storeDef->hasTag('lock.store'));
            $this->assertFalse($container->getDefinition('.lock.flock.store')->hasTag('lock.store'));
        } else {
            $this->assertSame('.lock.flock.store', $storeId);
            $this->assertSame(FlockStore::class, $storeDef->getClass());
            $this->assertTrue($storeDef->hasTag('lock.store'));
            $this->assertFalse($container->getDefinition('.lock.semaphore.store')->hasTag('lock.store'));
        }

        $this->assertSame('lock.default.factory', (string) $container->getAlias('lock.factory'));
        $this->assertSame('lock.factory', (string) $container->getAlias(LockFactory::class));
    }

    public function testNamedLocks()
    {
        $container = $this->createContainerFromFile('lock_named');

        $this->assertTrue($container->hasDefinition('lock.foo.factory'));
        $storeId = (string) $container->getDefinition('lock.foo.factory')->getArgument(0);
        $storeDef = $container->getDefinition($storeId);
        $this->assertSame('.lock.semaphore.store', $storeId);
        $this->assertSame(SemaphoreStore::class, $storeDef->getClass());
        $this->assertSame('%kernel.project_dir%', $storeDef->getArgument(0));
        $this->assertTrue($storeDef->hasTag('lock.store'));

        $this->assertTrue($container->hasDefinition('lock.bar.factory'));
        $storeId = (string) $container->getDefinition('lock.bar.factory')->getArgument(0);
        $storeDef = $container->getDefinition($storeId);
        $this->assertSame('.lock.flock.store', $storeId);
        $this->assertSame(FlockStore::class, $storeDef->getClass());
        $this->assertTrue($storeDef->hasTag('lock.store'));

        $this->assertTrue($container->hasDefinition('lock.baz.factory'));
        $storeDef = $container->getDefinition($container->getDefinition('lock.baz.factory')->getArgument(0));
        $this->assertIsArray($storeDefArg = $storeDef->getArgument(0));
        $this->assertSame(['.lock.semaphore.store', '.lock.flock.store'], array_map('strval', $storeDefArg));

        $this->assertTrue($container->hasDefinition('lock.qux.factory'));
        $storeDef = $container->getDefinition($container->getDefinition('lock.qux.factory')->getArgument(0));
        $this->assertStringContainsString('REDIS_DSN', $storeDef->getArgument(0));

        $this->assertTrue($container->hasDefinition('lock.corge.factory'));
        $storeDef = $container->getDefinition($container->getDefinition('lock.corge.factory')->getArgument(0));
        $this->assertSame('in-memory', $storeDef->getArgument(0));

        $this->assertTrue($container->hasDefinition('lock.grault.factory'));
        $storeDef = $container->getDefinition($container->getDefinition('lock.grault.factory')->getArgument(0));
        $this->assertSame('mysql:host=localhost;dbname=test', $storeDef->getArgument(0));

        $this->assertTrue($container->hasDefinition('lock.garply.factory'));
        $storeDef = $container->getDefinition($container->getDefinition('lock.garply.factory')->getArgument(0));
        $this->assertSame('null', $storeDef->getArgument(0));

        $this->assertFalse($container->hasAlias('lock.factory'));
    }

    public function testLockWithService()
    {
        $container = $this->createContainerFromFile('lock_service');

        $this->assertTrue($container->hasDefinition('lock.default.factory'));
        $storeDef = $container->getDefinition($container->getDefinition('lock.default.factory')->getArgument(0));
        $this->assertEquals(new Reference('my_service'), $storeDef->getArgument(0));
    }

    public function testLockWithAdvisoryService()
    {
        $container = $this->createContainerFromFile('lock_advisory');

        $storeDef = $container->getDefinition($container->getDefinition('lock.foo.factory')->getArgument(0));
        $this->assertEquals([new Reference('my_connection'), true], $storeDef->getArguments());

        $storeDef = $container->getDefinition($container->getDefinition('lock.bar.factory')->getArgument(0));
        $this->assertEquals([new Reference('my_connection')], $storeDef->getArguments());

        $combinedDef = $container->getDefinition($container->getDefinition('lock.baz.factory')->getArgument(0));
        $this->assertIsArray($storeRefs = $combinedDef->getArgument(0));
        $this->assertCount(2, $storeRefs);
        $this->assertSame('.lock.flock.store', (string) $storeRefs[0]);
        $this->assertEquals([new Reference('my_connection'), true], $container->getDefinition((string) $storeRefs[1])->getArguments());
    }

    public function testLockWithServiceAndEnv()
    {
        $container = $this->createContainerFromFile('lock_service_and_env');

        $this->assertTrue($container->hasDefinition('lock.foo.factory'));
        $this->assertTrue($container->hasDefinition('lock.bar.factory'));
        $storeDef = $container->getDefinition($container->getDefinition('lock.bar.factory')->getArgument(0));

        $connection = $storeDef->getArgument(0);
        $this->assertInstanceOf(Reference::class, $connection);
        $this->assertSame('my_service', (string) $connection);
    }

    public function testLockDisabled()
    {
        $container = $this->createContainerFromFile('lock_disabled');

        $this->assertFalse($container->hasDefinition('lock.factory.abstract'));
        $this->assertFalse($container->hasDefinition('lock.default.factory'));
        $this->assertFalse($container->hasDefinition('serializer.normalizer.lock_key'));
    }

    private function createContainerFromFile(string $file): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag(['kernel.debug' => false, 'kernel.project_dir' => __DIR__]));
        $container->registerExtension(new LockBundle()->getContainerExtension());
        new PhpFileLoader($container, new FileLocator(__DIR__.'/Fixtures'))->load($file.'.php');
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([]);
        $container->getCompilerPassConfig()->setOptimizationPasses([new ResolveChildDefinitionsPass()]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

        return $container;
    }
}
