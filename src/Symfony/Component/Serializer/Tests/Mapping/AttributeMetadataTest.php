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
use Symfony\Component\PropertyAccess\PropertyPath;
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
        $this->assertInstanceOf(AttributeMetadataInterface::class, $attributeMetadata);
    }

    public function testGetName()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $this->assertEquals('name', $attributeMetadata->getName());
    }

    public function testGroups()
    {
        $attributeMetadata = new AttributeMetadata('group');
        $attributeMetadata->addGroup('a');
        $attributeMetadata->addGroup('a');
        $attributeMetadata->addGroup('b');

        $this->assertEquals(['a', 'b'], $attributeMetadata->getGroups());
    }

    public function testMaxDepth()
    {
        $attributeMetadata = new AttributeMetadata('name');
        $attributeMetadata->setMaxDepth(69);

        $this->assertEquals(69, $attributeMetadata->getMaxDepth());
    }

    public function testSerializedNames()
    {
        $attributeMetadata = new AttributeMetadata('name');

        $this->assertSame([], $attributeMetadata->getSerializedNames());

        $attributeMetadata->setSerializedNames([
            'foo-group' => 'foo',
            'bar-group' => 'bar',
        ]);

        $this->assertEquals([
            'foo-group' => 'foo',
            'bar-group' => 'bar',
        ], $attributeMetadata->getSerializedNames());

        $attributeMetadata->setSerializedName('baz', ['baz-group']);
        $this->assertEquals([
            'foo-group' => 'foo',
            'bar-group' => 'bar',
            'baz-group' => 'baz',
        ], $attributeMetadata->getSerializedNames());

        $attributeMetadata->setSerializedName('bar', ['baz-group']);
        $this->assertEquals([
            'foo-group' => 'foo',
            'bar-group' => 'bar',
            'baz-group' => 'bar',
        ], $attributeMetadata->getSerializedNames());

        $this->assertNull($attributeMetadata->getSerializedName(['unknown']));
        $this->assertSame('bar', $attributeMetadata->getSerializedName(['bar-group']));
        $this->assertSame('foo', $attributeMetadata->getSerializedName(['foo-group', 'bar-group']));
        $this->assertSame('bar', $attributeMetadata->getSerializedName(['bar-group', 'foo-group']));
    }

    public function testSerializedNamesWithoutSpecificGroup()
    {
        $attributeMetadata = new AttributeMetadata('name');

        $attributeMetadata->setSerializedName('foo', []);
        $this->assertSame('foo', $attributeMetadata->getSerializedName([]));
        $this->assertSame('foo', $attributeMetadata->getSerializedName(['bar']));

        $this->assertSame([
            '*' => 'foo',
        ], $attributeMetadata->getSerializedNames());
    }

    public function testNullSerializedNames()
    {
        $attributeMetadata = new AttributeMetadata('name');

        $attributeMetadata->setSerializedName(null, []);

        $this->assertSame([
            '*' => null,
        ], $attributeMetadata->getSerializedNames());
    }

    public function testSerializedPath()
    {
        $attributeMetadata = new AttributeMetadata('path');
        $serializedPath = new PropertyPath('[serialized][path]');
        $attributeMetadata->setSerializedPath($serializedPath);

        $this->assertEquals($serializedPath, $attributeMetadata->getSerializedPath());
    }

    public function testIgnore()
    {
        $attributeMetadata = new AttributeMetadata('ignored');
        $this->assertFalse($attributeMetadata->isIgnored());
        $attributeMetadata->setIgnore(true);
        $this->assertTrue($attributeMetadata->isIgnored());
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
        $serializedPath = new PropertyPath('[a4][a5]');
        $attributeMetadata1 = new AttributeMetadata('a1');
        $attributeMetadata1->addGroup('a');
        $attributeMetadata1->addGroup('b');
        $attributeMetadata1->setSerializedNames([
            'group-a' => 'name-a',
            'group-b' => 'name-b',
        ]);

        $attributeMetadata2 = new AttributeMetadata('a2');
        $attributeMetadata2->addGroup('a');
        $attributeMetadata2->addGroup('c');
        $attributeMetadata2->setMaxDepth(2);
        $attributeMetadata2->setSerializedName('a3');
        $attributeMetadata2->setSerializedPath($serializedPath);
        $attributeMetadata2->setNormalizationContextForGroups(['foo' => 'bar'], ['a']);
        $attributeMetadata2->setDenormalizationContextForGroups(['baz' => 'qux'], ['c']);

        $attributeMetadata2->setIgnore(true);

        $attributeMetadata1->merge($attributeMetadata2);

        $this->assertEquals(['a', 'b', 'c'], $attributeMetadata1->getGroups());
        $this->assertEquals(2, $attributeMetadata1->getMaxDepth());
        $this->assertEquals([
            'group-a' => 'name-a',
            'group-b' => 'name-b',
        ], $attributeMetadata1->getSerializedNames());
        $this->assertEquals($serializedPath, $attributeMetadata1->getSerializedPath());
        $this->assertSame(['a' => ['foo' => 'bar']], $attributeMetadata1->getNormalizationContexts());
        $this->assertSame(['c' => ['baz' => 'qux']], $attributeMetadata1->getDenormalizationContexts());
        $this->assertTrue($attributeMetadata1->isIgnored());
    }

    public function testContextsNotMergedIfAlreadyDefined()
    {
        $attributeMetadata1 = new AttributeMetadata('a1');
        $attributeMetadata1->setSerializedNames([
            'group-a' => 'name-a',
            'group-b' => 'name-b',
        ]);

        $attributeMetadata2 = new AttributeMetadata('a2');
        $attributeMetadata2->setSerializedNames([
            'group-b' => 'name-c',
        ]);

        $attributeMetadata1->merge($attributeMetadata2);

        self::assertSame([
            'group-a' => 'name-a',
            'group-b' => 'name-b',
        ], $attributeMetadata1->getSerializedNames());
    }

    public function testSerializedNamesNotMergedIfAlreadyDefined()
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
        $attributeMetadata->setSerializedNames([
            'group-a' => 'name-a',
        ]);
        $serializedPath = new PropertyPath('[serialized][path]');
        $attributeMetadata->setSerializedPath($serializedPath);

        $serialized = serialize($attributeMetadata);
        $this->assertEquals($attributeMetadata, unserialize($serialized));
    }

    public function testSerializeSerializedNameNullCompatibility()
    {
        $attributeMetadata = new AttributeMetadata('attribute');
        $attributeMetadata->serializedName = null;

        $serialized = serialize($attributeMetadata);
        $unserialized = unserialize($serialized);

        $this->assertSame([], $unserialized->serializedName);
        $this->assertNull($unserialized->getSerializedName());
    }

    public function testSerializeSerializedNameStringCompatibility()
    {
        $attributeMetadata = new AttributeMetadata('attribute');
        $attributeMetadata->serializedName = 'foo';

        $serialized = serialize($attributeMetadata);
        $unserialized = unserialize($serialized);

        $this->assertSame([
            '*' => 'foo',
        ], $unserialized->serializedName);
        $this->assertSame('foo', $unserialized->getSerializedName());
    }
}
