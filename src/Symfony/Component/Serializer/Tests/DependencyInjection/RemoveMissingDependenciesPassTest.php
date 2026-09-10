<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\RemoveMissingDependenciesPass as ContainerRemoveMissingDependenciesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Serializer\DependencyInjection\RemoveMissingDependenciesPass;

class RemoveMissingDependenciesPassTest extends TestCase
{
    public function testThePropertyAccessDependentsAreKeptWhenAPropertyAccessorIsRegistered()
    {
        $container = $this->createContainer();
        $container->register('property_accessor');

        new ContainerRemoveMissingDependenciesPass()->process($container);
        new RemoveMissingDependenciesPass()->process($container);

        $this->assertTrue($container->has('serializer.property_accessor'));
        $this->assertTrue($container->has('serializer.normalizer.object'));
        $this->assertTrue($container->has('serializer.denormalizer.unwrapping'));
    }

    public function testThePropertyAccessDependentsAreDroppedWithoutAPropertyAccessor()
    {
        $container = $this->createContainer();

        new ContainerRemoveMissingDependenciesPass()->process($container);
        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->has('serializer.property_accessor'));
        $this->assertFalse($container->has('serializer.normalizer.object'));
        $this->assertFalse($container->has('serializer.denormalizer.unwrapping'));
    }

    public function testTheTranslatableNormalizerGoesWithTheTranslator()
    {
        $container = $this->createContainer();

        new ContainerRemoveMissingDependenciesPass()->process($container);
        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->has('serializer.normalizer.translatable'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.project_dir' => '/app',
            'kernel.build_dir' => sys_get_temp_dir(),
            'kernel.cache_dir' => sys_get_temp_dir(),
        ]));
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/Resources/config'));
        $loader->load('serializer.php');

        return $container;
    }
}
