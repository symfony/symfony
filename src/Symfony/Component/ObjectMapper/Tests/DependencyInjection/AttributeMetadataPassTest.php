<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\ObjectMapper\DependencyInjection\AttributeMetadataPass;
use Symfony\Component\ObjectMapper\Tests\Fixtures\A;
use Symfony\Component\ObjectMapper\Tests\Fixtures\B;
use Symfony\Component\ObjectMapper\Tests\Fixtures\C;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassWithoutTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\D;

class AttributeMetadataPassTest extends TestCase
{
    public function testProcessWithNoWarmer()
    {
        $container = new ContainerBuilder();
        (new AttributeMetadataPass())->process($container);
        $this->expectNotToPerformAssertions();
    }

    public function testProcessWithWarmerButNoTaggedServices()
    {
        $container = new ContainerBuilder();
        $container->register('object_mapper.cached.cache_warmer');

        (new AttributeMetadataPass())->process($container);

        $this->assertCount(0, $container->getDefinition('object_mapper.cached.cache_warmer')->getArguments());
    }

    public function testProcessThrowsExceptionForMissingExcludeTag()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The resource "%s" with a "Map" attribute must be tagged with "container.excluded".', A::class));

        $container = new ContainerBuilder();
        $container->register('object_mapper.cached.cache_warmer', \stdClass::class)->addArgument([]);
        $container->register(A::class)
            ->addTag('object_mapper.attribute_metadata', ['source' => A::class, 'target' => B::class]);

        (new AttributeMetadataPass())->process($container);
    }

    public function testProcessWithTaggedServices()
    {
        $container = new ContainerBuilder();
        $container->setParameter('source.class_a', A::class);
        $container->register('object_mapper.cached.cache_warmer', \stdClass::class)->addArgument([]);

        $container->register('service1', '%source.class_a%')
            ->addTag('object_mapper.attribute_metadata', ['source' => '%source.class_a%', 'target' => B::class])
            ->addTag('container.excluded');
        $container->register('service2', C::class)
            ->addTag('object_mapper.attribute_metadata', ['source' => C::class, 'target' => D::class])
            ->addTag('container.excluded');
        $container->register('service3', ClassWithoutTarget::class)
             ->addTag('object_mapper.attribute_metadata', ['source' => ClassWithoutTarget::class])
            ->addTag('container.excluded');

        (new AttributeMetadataPass())->process($container);

        $warmerDef = $container->getDefinition('object_mapper.cached.cache_warmer');
        $this->assertCount(1, $warmerDef->getArguments());
        $mappedPairs = $warmerDef->getArgument(0);

        $expectedPairs = [
            ['source' => A::class, 'target' => B::class],
            ['source' => C::class, 'target' => D::class],
        ];

        $this->assertCount(2, $mappedPairs);
        $this->assertEquals($expectedPairs, $mappedPairs);

        $this->assertFalse($container->hasDefinition('service1'));
        $this->assertFalse($container->hasDefinition('service2'));
        $this->assertFalse($container->hasDefinition('service3'));
    }
}
