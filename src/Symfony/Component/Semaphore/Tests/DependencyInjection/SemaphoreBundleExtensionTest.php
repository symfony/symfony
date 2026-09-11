<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Semaphore\SemaphoreBundle;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\StoreFactory;

class SemaphoreBundleExtensionTest extends TestCase
{
    public function testDefaultSemaphore()
    {
        $container = $this->createContainerFromFile('semaphore');

        $this->assertTrue($container->hasDefinition('semaphore.default.factory'));
        $storeDef = $container->getDefinition($container->getDefinition('semaphore.default.factory')->getArgument(0));
        $this->assertSame([StoreFactory::class, 'createStore'], $storeDef->getFactory());
        $this->assertSame('redis://localhost', $storeDef->getArgument(0));

        $this->assertSame('semaphore.default.factory', (string) $container->getAlias('semaphore.factory'));
        $this->assertSame('semaphore.factory', (string) $container->getAlias(SemaphoreFactory::class));
    }

    public function testSemaphoreFromARootLevelDsn()
    {
        $config = (new \ReflectionMethod(ContainerConfigurator::class, 'extension'))->getParameters()[1]->getType();

        if ($config instanceof \ReflectionNamedType && 'array' === $config->getName()) {
            $this->markTestSkipped('symfony/dependency-injection >= 8.2 is required to pass a value that is not an array to an extension.');
        }

        $container = $this->createContainerFromFile('semaphore_dsn');

        $this->assertTrue($container->hasDefinition('semaphore.default.factory'));
        $storeDef = $container->getDefinition($container->getDefinition('semaphore.default.factory')->getArgument(0));
        $this->assertSame([StoreFactory::class, 'createStore'], $storeDef->getFactory());
        $this->assertSame('redis://example.com', $storeDef->getArgument(0));
    }

    public function testNamedSemaphores()
    {
        $container = $this->createContainerFromFile('semaphore_named');

        $this->assertTrue($container->hasDefinition('semaphore.foo.factory'));
        $storeDef = $container->getDefinition($container->getDefinition('semaphore.foo.factory')->getArgument(0));
        $this->assertSame('redis://paas.com', $storeDef->getArgument(0));

        $this->assertTrue($container->hasDefinition('semaphore.qux.factory'));
        $storeDef = $container->getDefinition($container->getDefinition('semaphore.qux.factory')->getArgument(0));
        $this->assertStringContainsString('REDIS_DSN', $storeDef->getArgument(0));

        $this->assertFalse($container->hasAlias('semaphore.factory'));
    }

    public function testSemaphoreWithService()
    {
        $container = $this->createContainerFromFile('semaphore_service');

        $this->assertTrue($container->hasDefinition('semaphore.default.factory'));
        $storeDef = $container->getDefinition($container->getDefinition('semaphore.default.factory')->getArgument(0));
        $this->assertEquals(new Reference('my_service'), $storeDef->getArgument(0));
    }

    public function testSemaphoreWithLock()
    {
        $container = $this->createContainerFromFile('semaphore_lock');

        $this->assertTrue($container->hasDefinition('semaphore.default.factory'));
        $storeDef = $container->getDefinition($container->getDefinition('semaphore.default.factory')->getArgument(0));
        $this->assertSame([StoreFactory::class, 'createStore'], $storeDef->getFactory());
        $this->assertEquals(new Reference('lock.default.factory'), $storeDef->getArgument(0));
    }

    public function testSemaphoreWithNamedLock()
    {
        $container = $this->createContainerFromFile('semaphore_lock_named');

        $this->assertTrue($container->hasDefinition('semaphore.default.factory'));
        $storeDef = $container->getDefinition($container->getDefinition('semaphore.default.factory')->getArgument(0));
        $this->assertSame([StoreFactory::class, 'createStore'], $storeDef->getFactory());
        $this->assertEquals(new Reference('lock.default.factory'), $storeDef->getArgument(0));

        $this->assertTrue($container->hasDefinition('semaphore.bar.factory'));
        $storeDef = $container->getDefinition($container->getDefinition('semaphore.bar.factory')->getArgument(0));
        $this->assertSame([StoreFactory::class, 'createStore'], $storeDef->getFactory());
        $this->assertEquals(new Reference('lock.foo.factory'), $storeDef->getArgument(0));
    }

    public function testSemaphoreEnabledByDefault()
    {
        $container = $this->createContainerFromFile('semaphore_enabled');

        $this->assertTrue($container->hasDefinition('semaphore.factory.abstract'));
        $this->assertFalse($container->hasDefinition('semaphore.default.factory'));
    }

    public function testSemaphoreDisabled()
    {
        $container = $this->createContainerFromFile('semaphore_disabled');

        $this->assertFalse($container->hasDefinition('semaphore.factory.abstract'));
        $this->assertFalse($container->hasDefinition('serializer.normalizer.semaphore_key'));
    }

    private function createContainerFromFile(string $file): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->registerExtension(new SemaphoreBundle()->getContainerExtension());
        (new PhpFileLoader($container, new FileLocator(__DIR__.'/Fixtures')))->load($file.'.php');
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([]);
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

        return $container;
    }
}
