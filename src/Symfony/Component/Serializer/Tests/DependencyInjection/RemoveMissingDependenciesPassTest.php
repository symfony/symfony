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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Serializer\DependencyInjection\RemoveMissingDependenciesPass;

class RemoveMissingDependenciesPassTest extends TestCase
{
    public function testThePropertyAccessDependentsAreKeptWhenAPropertyAccessorIsRegistered()
    {
        $container = $this->createContainer();
        $container->register('property_accessor');

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertTrue($container->has('serializer.property_accessor'));
        $this->assertTrue($container->has('serializer.normalizer.object'));
        $this->assertTrue($container->has('serializer.denormalizer.unwrapping'));
    }

    public function testThePropertyAccessDependentsAreDroppedWithoutAPropertyAccessor()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->has('serializer.property_accessor'));
        $this->assertFalse($container->has('serializer.normalizer.object'));
        $this->assertFalse($container->has('serializer.denormalizer.unwrapping'));
    }

    public function testTheTranslatableNormalizerGoesWithTheTranslator()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->has('serializer.normalizer.translatable'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setAlias('serializer.property_accessor', 'property_accessor');

        foreach ([
            'serializer.normalizer.object',
            'serializer.denormalizer.unwrapping',
            'serializer.normalizer.translatable',
        ] as $id) {
            $container->register($id);
        }

        return $container;
    }
}
