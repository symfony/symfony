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
use Symfony\Component\Config\FileLocator;
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
    }

    private function createContainerFromFile(string $file): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
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
