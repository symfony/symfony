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
use Symfony\Component\JsonEncoder\Mapping\Decode\AttributePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadata;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Attribute\BooleanStringDecodeFormatter;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithFormatterAttributes;
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

    public function testRetrieveDecodeFormatter()
    {
        $typeResolver = TypeResolver::create();
        $loader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader($typeResolver), $typeResolver);

        $this->assertEquals([
            'id' => new PropertyMetadata('id', Type::string(), [DummyWithFormatterAttributes::divideAndCastToInt(...)]),
            'name' => new PropertyMetadata('name', Type::string(), [strtolower(...)]),
            'active' => new PropertyMetadata('active', Type::string(), [BooleanStringDecodeFormatter::toBool(...)]),
        ], $loader->load(DummyWithFormatterAttributes::class, [], []));
    }
}
