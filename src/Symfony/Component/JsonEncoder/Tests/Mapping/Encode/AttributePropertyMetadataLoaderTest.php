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
use Symfony\Component\JsonEncoder\Mapping\Encode\AttributePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadata;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Attribute\BooleanStringEncodeFormatter;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithFormatterAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithMaxDepthAttribute;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class AttributePropertyMetadataLoaderTest extends TestCase
{
    public function testRetrieveEncodedName()
    {
        $typeResolver = TypeResolver::create();
        $loader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader($typeResolver), $typeResolver);

        $this->assertSame(['@id', 'name'], array_keys($loader->load(DummyWithNameAttributes::class, [], [])));
    }

    public function testRetrieveEncodeFormatter()
    {
        $typeResolver = TypeResolver::create();
        $loader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader($typeResolver), $typeResolver);

        $this->assertEquals([
            'id' => new PropertyMetadata('id', Type::string(), [DummyWithFormatterAttributes::doubleAndCastToString(...)]),
            'name' => new PropertyMetadata('name', Type::string(), [strtoupper(...)]),
            'active' => new PropertyMetadata('active', Type::string(), [BooleanStringEncodeFormatter::toString(...)]),
        ], $loader->load(DummyWithFormatterAttributes::class, [], []));
    }

    public function testRetrieveMaxDepthFormatter()
    {
        $typeResolver = TypeResolver::create();
        $loader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader($typeResolver), $typeResolver);

        $this->assertEquals([
            'id' => new PropertyMetadata('id', Type::int(), []),
        ], $loader->load(DummyWithMaxDepthAttribute::class, [], []));

        $this->assertEquals([
            'id' => new PropertyMetadata('id', Type::bool(), [DummyWithMaxDepthAttribute::boolean(...)]),
        ], $loader->load(DummyWithMaxDepthAttribute::class, [], [
            'depth_counters' => [DummyWithMaxDepthAttribute::class => 256],
        ]));
    }
}
