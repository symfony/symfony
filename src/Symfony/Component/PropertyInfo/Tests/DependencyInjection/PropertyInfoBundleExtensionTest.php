<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\PropertyInfo\PropertyInfoBundle;

class PropertyInfoBundleExtensionTest extends TestCase
{
    public function testPropertyInfoEnabled()
    {
        $container = $this->createContainerFromFile('property_info');

        $this->assertTrue($container->has('property_info'));
        $this->assertTrue($container->has('property_info.reflection_extractor'));
        $this->assertTrue($container->has('property_info.constructor_extractor'));
    }

    public function testPropertyInfoWithConstructorExtractorDisabled()
    {
        $container = $this->createContainerFromFile('property_info_without_constructor_extractor');

        $this->assertTrue($container->has('property_info'));
        $this->assertFalse($container->has('property_info.constructor_extractor'));
    }

    public function testDisabledPropertyInfoRegistersNoService()
    {
        $container = $this->createContainerFromFile('property_info_disabled');

        $this->assertFalse($container->has('property_info'));
        $this->assertFalse($container->has('cache.property_info'));
    }

    public function testPropertyInfoCacheActivated()
    {
        $container = $this->createContainerFromFile('property_info');

        $this->assertTrue($container->hasDefinition('property_info.cache'));
        $this->assertEquals(new Reference('cache.property_info'), $container->getDefinition('property_info.cache')->getArgument(1));
        $this->assertSame('cache.system', $container->getDefinition('cache.property_info')->getParent());
    }

    public function testPropertyInfoCacheDisabledInDebug()
    {
        $container = $this->createContainerFromFile('property_info', true);

        $this->assertFalse($container->hasDefinition('property_info.cache'));
    }

    private function createContainerFromFile(string $file, bool $debug = false): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', $debug);
        $container->registerExtension(new PropertyInfoBundle()->getContainerExtension());
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
