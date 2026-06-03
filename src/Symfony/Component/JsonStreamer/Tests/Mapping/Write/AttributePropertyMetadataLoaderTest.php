<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Mapping\Write;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadata;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\Write\AttributePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueObjectTransformerAttributes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Transformer\BooleanToStringValueTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Transformer\DoubleIntAndCastToStringValueTransformer;
use Symfony\Component\JsonStreamer\Tests\ServiceContainer;
use Symfony\Component\JsonStreamer\Transformer\DateTimeValueObjectTransformer;
use Symfony\Component\JsonStreamer\Transformer\PropertyValueTransformerInterface;
use Symfony\Component\JsonStreamer\Transformer\ValueObjectTransformerInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class AttributePropertyMetadataLoaderTest extends TestCase
{
    public function testRetrieveStreamedName()
    {
        $loader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader(TypeResolver::create()), new ServiceContainer(), TypeResolver::create());

        $this->assertSame(['@id', 'name'], array_keys($loader->load(DummyWithNameAttributes::class)));
    }

    public function testRetrieveValueTransformer()
    {
        $loader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader(TypeResolver::create()), new ServiceContainer([
            DoubleIntAndCastToStringValueTransformer::class => new DoubleIntAndCastToStringValueTransformer(),
            BooleanToStringValueTransformer::class => new BooleanToStringValueTransformer(),
        ]), TypeResolver::create());

        $this->assertEquals([
            'id' => new PropertyMetadata('id', Type::string(), [DoubleIntAndCastToStringValueTransformer::class]),
            'active' => new PropertyMetadata('active', Type::string(), [BooleanToStringValueTransformer::class]),
            'name' => new PropertyMetadata('name', Type::string(), [\Closure::fromCallable('strtolower')]),
            'range' => new PropertyMetadata('range', Type::string(), [\Closure::fromCallable(DummyWithValueTransformerAttributes::concatRange(...))]),
        ], $loader->load(DummyWithValueTransformerAttributes::class));
    }

    public function testThrowWhenCannotRetrieveValueTransformer()
    {
        $loader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader(TypeResolver::create()), new ServiceContainer(), TypeResolver::create());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('You have requested a non-existent property value transformer service "%s". Did you implement "%s"?', DoubleIntAndCastToStringValueTransformer::class, PropertyValueTransformerInterface::class));

        $loader->load(DummyWithValueTransformerAttributes::class);
    }

    public function testThrowWhenValueObjectTransformer()
    {
        $loader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader(TypeResolver::create()), new ServiceContainer([
            DateTimeValueObjectTransformer::class => new DateTimeValueObjectTransformer(),
        ]), TypeResolver::create());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('"%s" is a "%s" and must not be specified as a property value transformer.', DateTimeValueObjectTransformer::class, ValueObjectTransformerInterface::class));

        $loader->load(DummyWithValueObjectTransformerAttributes::class);
    }

    public function testThrowWhenInvalidValueTransformer()
    {
        $loader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader(TypeResolver::create()), new ServiceContainer([
            DoubleIntAndCastToStringValueTransformer::class => true,
            BooleanToStringValueTransformer::class => new BooleanToStringValueTransformer(),
        ]), TypeResolver::create());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" property value transformer service does not implement "%s".', DoubleIntAndCastToStringValueTransformer::class, PropertyValueTransformerInterface::class));

        $loader->load(DummyWithValueTransformerAttributes::class);
    }
}
