<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Mapping\Loader\Features;

use PHPUnit\Framework\Assert;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\ContextDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\ContextDummyParent;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
trait ContextMappingTestTrait
{
    abstract protected function getLoaderForContextMapping(): LoaderInterface;

    public function testLoadContexts()
    {
        $this->assertLoadedContexts();
    }

    public function assertLoadedContexts(string $dummyClass = ContextDummy::class, string $parentClass = ContextDummyParent::class)
    {
        $loader = $this->getLoaderForContextMapping();

        $classMetadata = new ClassMetadata($dummyClass);
        $parentClassMetadata = new ClassMetadata($parentClass);

        $loader->loadClassMetadata($parentClassMetadata);
        $classMetadata->merge($parentClassMetadata);

        $loader->loadClassMetadata($classMetadata);

        $attributes = $classMetadata->getAttributesMetadata();

        Assert::assertEquals(['*' => ['prop' => 'dummy_parent_value']], $attributes['parentProperty']->getNormalizationContexts());
        Assert::assertEquals(['*' => ['prop' => 'dummy_value']], $attributes['overriddenParentProperty']->getNormalizationContexts());

        Assert::assertEquals([
            '*' => [
                'foo' => 'value',
                'bar' => 'value',
                'nested' => ['nested_key' => 'nested_value'],
                'array' => ['first', 'second'],
            ],
            'a' => ['bar' => 'value_for_group_a'],
        ], $attributes['foo']->getNormalizationContexts());
        Assert::assertSame(
            $attributes['foo']->getNormalizationContexts(),
            $attributes['foo']->getDenormalizationContexts()
        );

        Assert::assertEquals([
            'a' => $c = ['format' => 'd/m/Y'],
            'b' => $c,
        ], $attributes['bar']->getNormalizationContexts());
        Assert::assertEquals([
            'a' => $c = ['format' => 'm-d-Y H:i'],
            'b' => $c,
        ], $attributes['bar']->getDenormalizationContexts());

        Assert::assertEquals(['*' => ['method' => 'method_with_context']], $attributes['methodWithContext']->getNormalizationContexts());
        Assert::assertEquals(
            $attributes['methodWithContext']->getNormalizationContexts(),
            $attributes['methodWithContext']->getDenormalizationContexts()
        );
    }
}
