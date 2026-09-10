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
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\DependencyInjection\SerializerPass;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;
use Symfony\Component\Serializer\SerializerBundle;

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

    public function testTheNormalizersAreRemovedBeforeSerializerPassCollectsThem()
    {
        // SerializerBundle is a required bundle, so it is built first and registers
        // SerializerPass ahead of everything this bundle registers at the default priority
        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => false]));
        new SerializerBundle()->build($container);
        new FrameworkBundle()->build($container);

        $order = [];
        foreach ($container->getCompiler()->getPassConfig()->getBeforeOptimizationPasses() as $i => $pass) {
            $order[$pass::class] = $i;
        }

        $this->assertArrayHasKey(SerializerPass::class, $order);
        $this->assertArrayHasKey(RemoveUnusedSerializerPropertyAccessorPass::class, $order);
        $this->assertLessThan($order[SerializerPass::class], $order[RemoveUnusedSerializerPropertyAccessorPass::class]);
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
