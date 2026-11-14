<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\PropertyAccess\PropertyAccessBundle;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class PropertyAccessBundleExtensionTest extends TestCase
{
    public function testPropertyAccessWithDefaultValue()
    {
        $container = $this->createContainerFromFile('property_accessor');

        $def = $container->getDefinition('property_accessor');
        $this->assertSame(PropertyAccessor::MAGIC_SET | PropertyAccessor::MAGIC_GET, $def->getArgument(0));
        $this->assertSame(PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH, $def->getArgument(1));
        $this->assertFalse($def->getArgument(5));
    }

    public function testPropertyAccessWithOverriddenValues()
    {
        $container = $this->createContainerFromFile('property_accessor_overridden');

        $def = $container->getDefinition('property_accessor');
        $this->assertSame(PropertyAccessor::MAGIC_GET | PropertyAccessor::MAGIC_CALL, $def->getArgument(0));
        $this->assertSame(PropertyAccessor::THROW_ON_INVALID_INDEX, $def->getArgument(1));
        $this->assertTrue($def->getArgument(5));
    }

    public function testDisabledPropertyAccessRegistersNoService()
    {
        $container = $this->createContainerFromFile('property_accessor_disabled');

        $this->assertFalse($container->has('property_accessor'));
        $this->assertFalse($container->has('cache.property_access'));
    }

    public function testOptionsReadByTheExtensionResolveEnvVars()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('env(PROPERTY_ACCESS_ENABLED)', '0');
        $container->registerExtension(new PropertyAccessBundle()->getContainerExtension());
        $container->loadFromExtension('property_access', array_fill_keys([
            'magic_call',
            'magic_get',
            'magic_set',
            'throw_exception_on_invalid_index',
            'throw_exception_on_invalid_property_path',
            'wildcard_reads',
        ], '%env(bool:PROPERTY_ACCESS_ENABLED)%'));

        new MergeExtensionConfigurationPass()->process($container);

        $definition = $container->getDefinition('property_accessor');
        $this->assertSame(PropertyAccessor::DISALLOW_MAGIC_METHODS, $definition->getArgument(0));
        $this->assertSame(PropertyAccessor::DO_NOT_THROW, $definition->getArgument(1));
        $this->assertSame('%env(bool:PROPERTY_ACCESS_ENABLED)%', $container->resolveEnvPlaceholders($definition->getArgument(5)));
    }

    public function testPropertyAccessCache()
    {
        $container = $this->createContainerFromFile('property_accessor');

        $cache = $container->getDefinition('cache.property_access');
        $this->assertSame([PropertyAccessor::class, 'createCache'], $cache->getFactory(), 'PropertyAccessor::createCache() should be used in non-debug mode');
        $this->assertSame(AdapterInterface::class, $cache->getClass());
        $this->assertSame([['clearer' => 'cache.system_clearer']], $cache->getTag('cache.pool'));
    }

    public function testPropertyAccessCacheWithDebug()
    {
        $container = $this->createContainerFromFile('property_accessor', true);

        $cache = $container->getDefinition('cache.property_access');
        $this->assertNull($cache->getFactory());
        $this->assertSame(ArrayAdapter::class, $cache->getClass(), 'ArrayAdapter should be used in debug mode');
    }

    private function createContainerFromFile(string $file, bool $debug = false): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', $debug);
        $container->registerExtension(new PropertyAccessBundle()->getContainerExtension());
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([]);
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);

        new PhpFileLoader($container, new FileLocator(__DIR__.'/Fixtures'))->load($file.'.php');
        $container->compile();

        return $container;
    }
}
