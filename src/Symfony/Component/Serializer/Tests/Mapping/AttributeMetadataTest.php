<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Mapping;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AttributeMetadataTest extends TestCase
{
    public function testInterface()
    {
        $attributeMetadata = new AttributeMetadata('name');
        self::assertInstanceOf(AttributeMetadataInterface::class, $attributeMetadata);
    }

    public function testGetName()
    {
        $attributeMetadata = new AttributeMetadata('name');
        self::assertEquals('name', $attributeMetadata->getName());
    }

    public function testGroups()
    {
        $attributeMetadata = new AttributeMetadata('group');
        $attributeMetadata->addGroup('a');
        $attributeMetadata->addGroup('a');
        $attributeMetadata->addGroup('b');

        self::assertEquals(['a', 'b'], $attributeMetadata->getGroups());
    }

    public function testMaxDepth()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $attributeMetadata->setMaxDepth(69);

        self::assertEquals(69, $attributeMetadata->getMaxDepth());
    }

    public function testSerializedName()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $attributeMetadata->setSerializedName('serialized_name');

        self::assertEquals('serialized_name', $attributeMetadata->getSerializedName());
    }

    public function testIgnore()
    {
        $attributeMetadata = new AttributeMetadata('ignored');
        self::assertFalse($attributeMetadata->isIgnored());
        $attributeMetadata->setIgnore(true);
        self::assertTrue($attributeMetadata->isIgnored());
    }

    public function testSetContexts()
    {
        $metadata = new AttributeMetadata('a1');
        $metadata->setNormalizationContextForGroups(['foo' => 'default', 'bar' => 'default'], []);
        $metadata->setNormalizationContextForGroups(['foo' => 'overridden'], ['a', 'b']);
        $metadata->setNormalizationContextForGroups(['bar' => 'overridden'], ['c']);

        self::assertSame([
            '*' => ['foo' => 'default', 'bar' => 'default'],
            'a' => ['foo' => 'overridden'],
            'b' => ['foo' => 'overridden'],
            'c' => ['bar' => 'overridden'],
        ], $metadata->getNormalizationContexts());

        $metadata->setDenormalizationContextForGroups(['foo' => 'default', 'bar' => 'default'], []);
        $metadata->setDenormalizationContextForGroups(['foo' => 'overridden'], ['a', 'b']);
        $metadata->setDenormalizationContextForGroups(['bar' => 'overridden'], ['c']);

        self::assertSame([
            '*' => ['foo' => 'default', 'bar' => 'default'],
            'a' => ['foo' => 'overridden'],
            'b' => ['foo' => 'overridden'],
            'c' => ['bar' => 'overridden'],
        ], $metadata->getDenormalizationContexts());
    }

    public function testGetContextsForGroups()
    {
        $metadata = new AttributeMetadata('a1');

        $metadata->setNormalizationContextForGroups(['foo' => 'default', 'bar' => 'default'], []);
        $metadata->setNormalizationContextForGroups(['foo' => 'overridden'], ['a', 'b']);
        $metadata->setNormalizationContextForGroups(['bar' => 'overridden'], ['c']);

        self::assertSame(['foo' => 'default', 'bar' => 'default'], $metadata->getNormalizationContextForGroups([]));
        self::assertSame(['foo' => 'overridden', 'bar' => 'default'], $metadata->getNormalizationContextForGroups(['a']));
        self::assertSame(['foo' => 'overridden', 'bar' => 'default'], $metadata->getNormalizationContextForGroups(['b']));
        self::assertSame(['foo' => 'default', 'bar' => 'overridden'], $metadata->getNormalizationContextForGroups(['c']));
        self::assertSame(['foo' => 'overridden', 'bar' => 'overridden'], $metadata->getNormalizationContextForGroups(['b', 'c']));

        $metadata->setDenormalizationContextForGroups(['foo' => 'default', 'bar' => 'default'], []);
        $metadata->setDenormalizationContextForGroups(['foo' => 'overridden'], ['a', 'b']);
        $metadata->setDenormalizationContextForGroups(['bar' => 'overridden'], ['c']);

        self::assertSame(['foo' => 'default', 'bar' => 'default'], $metadata->getDenormalizationContextForGroups([]));
        self::assertSame(['foo' => 'overridden', 'bar' => 'default'], $metadata->getDenormalizationContextForGroups(['a']));
        self::assertSame(['foo' => 'overridden', 'bar' => 'default'], $metadata->getDenormalizationContextForGroups(['b']));
        self::assertSame(['foo' => 'default', 'bar' => 'overridden'], $metadata->getDenormalizationContextForGroups(['c']));
        self::assertSame(['foo' => 'overridden', 'bar' => 'overridden'], $metadata->getDenormalizationContextForGroups(['b', 'c']));
    }

    public function testMerge()
    {
        $attributeMetadata1 = new AttributeMetadata('a1');
        $attributeMetadata1->addGroup('a');
        $attributeMetadata1->addGroup('b');

        $attributeMetadata2 = new AttributeMetadata('a2');
        $attributeMetadata2->addGroup('a');
        $attributeMetadata2->addGroup('c');
        $attributeMetadata2->setMaxDepth(2);
        $attributeMetadata2->setSerializedName('a3');
        $attributeMetadata2->setNormalizationContextForGroups(['foo' => 'bar'], ['a']);
        $attributeMetadata2->setDenormalizationContextForGroups(['baz' => 'qux'], ['c']);

        $attributeMetadata2->setIgnore(true);

        $attributeMetadata1->merge($attributeMetadata2);

        self::assertEquals(['a', 'b', 'c'], $attributeMetadata1->getGroups());
        self::assertEquals(2, $attributeMetadata1->getMaxDepth());
        self::assertEquals('a3', $attributeMetadata1->getSerializedName());
        self::assertSame(['a' => ['foo' => 'bar']], $attributeMetadata1->getNormalizationContexts());
        self::assertSame(['c' => ['baz' => 'qux']], $attributeMetadata1->getDenormalizationContexts());
        self::assertTrue($attributeMetadata1->isIgnored());
    }

    public function testContextsNotMergedIfAlreadyDefined()
    {
        $attributeMetadata1 = new AttributeMetadata('a1');
        $attributeMetadata1->setNormalizationContextForGroups(['foo' => 'not overridden'], ['a']);
        $attributeMetadata1->setDenormalizationContextForGroups(['baz' => 'not overridden'], ['b']);

        $attributeMetadata2 = new AttributeMetadata('a2');
        $attributeMetadata2->setNormalizationContextForGroups(['foo' => 'override'], ['a']);
        $attributeMetadata2->setDenormalizationContextForGroups(['baz' => 'override'], ['b']);

        $attributeMetadata1->merge($attributeMetadata2);

        self::assertSame(['a' => ['foo' => 'not overridden']], $attributeMetadata1->getNormalizationContexts());
        self::assertSame(['b' => ['baz' => 'not overridden']], $attributeMetadata1->getDenormalizationContexts());
    }

    public function testSerialize()
    {
        $attributeMetadata = new AttributeMetadata('attribute');
        $attributeMetadata->addGroup('a');
        $attributeMetadata->addGroup('b');
        $attributeMetadata->setMaxDepth(3);
        $attributeMetadata->setSerializedName('serialized_name');

        $serialized = serialize($attributeMetadata);
        self::assertEquals($attributeMetadata, unserialize($serialized));
    }
}
