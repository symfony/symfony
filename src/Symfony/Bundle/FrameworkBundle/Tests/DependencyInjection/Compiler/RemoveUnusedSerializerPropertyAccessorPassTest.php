<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RemoveUnusedSerializerPropertyAccessorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;

class RemoveUnusedSerializerPropertyAccessorPassTest extends TestCase
{
    public function testNormalizersAreKeptWhenThePropertyAccessorIsAvailable()
    {
        $container = $this->createContainer();
        $container->register('property_accessor', PropertyAccessor::class);

        new RemoveUnusedSerializerPropertyAccessorPass()->process($container);

        $this->assertTrue($container->hasAlias('serializer.property_accessor'));
        $this->assertTrue($container->hasDefinition('serializer.normalizer.object'));
        $this->assertTrue($container->hasDefinition('serializer.denormalizer.unwrapping'));
    }

    public function testNormalizersAreRemovedWhenThePropertyAccessorIsMissing()
    {
        $container = $this->createContainer();

        new RemoveUnusedSerializerPropertyAccessorPass()->process($container);

        $this->assertFalse($container->hasAlias('serializer.property_accessor'));
        $this->assertFalse($container->hasDefinition('serializer.normalizer.object'));
        $this->assertFalse($container->hasDefinition('serializer.denormalizer.unwrapping'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setAlias('serializer.property_accessor', 'property_accessor');
        $container->register('serializer.normalizer.object', ObjectNormalizer::class);
        $container->register('serializer.denormalizer.unwrapping', UnwrappingDenormalizer::class);

        return $container;
    }
}
