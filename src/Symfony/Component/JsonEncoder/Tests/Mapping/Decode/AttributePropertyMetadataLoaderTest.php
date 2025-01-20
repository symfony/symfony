<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\Mapping\Decode;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Decode\Denormalizer\DenormalizerInterface;
use Symfony\Component\JsonEncoder\Exception\InvalidArgumentException;
use Symfony\Component\JsonEncoder\Mapping\Decode\AttributePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadata;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Denormalizer\BooleanStringDenormalizer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Denormalizer\DivideStringAndCastToIntDenormalizer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNormalizerAttributes;
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

    public function testRetrieveDenormalizer()
    {
        $loader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader(TypeResolver::create()), new ServiceContainer([
            DivideStringAndCastToIntDenormalizer::class => new DivideStringAndCastToIntDenormalizer(),
            BooleanStringDenormalizer::class => new BooleanStringDenormalizer(),
        ]), TypeResolver::create());

        $this->assertEquals([
            'id' => new PropertyMetadata('id', Type::string(), [], [DivideStringAndCastToIntDenormalizer::class]),
            'active' => new PropertyMetadata('active', Type::string(), [], [BooleanStringDenormalizer::class]),
            'name' => new PropertyMetadata('name', Type::string(), [], [\Closure::fromCallable('strtolower')]),
            'range' => new PropertyMetadata('range', Type::string(), [], [\Closure::fromCallable(DummyWithNormalizerAttributes::concatRange(...))]),
        ], $loader->load(DummyWithNormalizerAttributes::class));
    }

    public function testThrowWhenCannotRetrieveDenormalizer()
    {
        $loader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader(TypeResolver::create()), new ServiceContainer(), TypeResolver::create());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('You have requested a non-existent denormalizer service "%s". Did you implement "%s"?', DivideStringAndCastToIntDenormalizer::class, DenormalizerInterface::class));

        $loader->load(DummyWithNormalizerAttributes::class);
    }

    public function testThrowWhenInvaliDenormalizer()
    {
        $loader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader(TypeResolver::create()), new ServiceContainer([
            DivideStringAndCastToIntDenormalizer::class => true,
            BooleanStringDenormalizer::class => new BooleanStringDenormalizer(),
        ]), TypeResolver::create());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" denormalizer service does not implement "%s".', DivideStringAndCastToIntDenormalizer::class, DenormalizerInterface::class));

        $loader->load(DummyWithNormalizerAttributes::class);
    }
}
