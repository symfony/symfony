<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\Mapping\Encode;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Encode\Normalizer\NormalizerInterface;
use Symfony\Component\JsonEncoder\Exception\InvalidArgumentException;
use Symfony\Component\JsonEncoder\Mapping\Encode\AttributePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadata;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNormalizerAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Normalizer\BooleanStringNormalizer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Normalizer\DoubleIntAndCastToStringNormalizer;
use Symfony\Component\JsonEncoder\Tests\ServiceContainer;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class AttributePropertyMetadataLoaderTest extends TestCase
{
    public function testRetrieveEncodedName()
    {
        $loader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader(TypeResolver::create()), new ServiceContainer(), TypeResolver::create());

        $this->assertSame(['@id', 'name'], array_keys($loader->load(DummyWithNameAttributes::class)));
    }

    public function testRetrieveNormalizer()
    {
        $loader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader(TypeResolver::create()), new ServiceContainer([
            DoubleIntAndCastToStringNormalizer::class => new DoubleIntAndCastToStringNormalizer(),
            BooleanStringNormalizer::class => new BooleanStringNormalizer(),
        ]), TypeResolver::create());

        $this->assertEquals([
            'id' => new PropertyMetadata('id', Type::string(), [DoubleIntAndCastToStringNormalizer::class]),
            'active' => new PropertyMetadata('active', Type::string(), [BooleanStringNormalizer::class]),
            'name' => new PropertyMetadata('name', Type::string(), [\Closure::fromCallable('strtolower')]),
            'range' => new PropertyMetadata('range', Type::string(), [\Closure::fromCallable(DummyWithNormalizerAttributes::concatRange(...))]),
        ], $loader->load(DummyWithNormalizerAttributes::class));
    }

    public function testThrowWhenCannotRetrieveNormalizer()
    {
        $loader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader(TypeResolver::create()), new ServiceContainer(), TypeResolver::create());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('You have requested a non-existent normalizer service "%s". Did you implement "%s"?', DoubleIntAndCastToStringNormalizer::class, NormalizerInterface::class));

        $loader->load(DummyWithNormalizerAttributes::class);
    }

    public function testThrowWhenInvalidNormalizer()
    {
        $loader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader(TypeResolver::create()), new ServiceContainer([
            DoubleIntAndCastToStringNormalizer::class => true,
            BooleanStringNormalizer::class => new BooleanStringNormalizer(),
        ]), TypeResolver::create());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" normalizer service does not implement "%s".', DoubleIntAndCastToStringNormalizer::class, NormalizerInterface::class));

        $loader->load(DummyWithNormalizerAttributes::class);
    }
}
