<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Annotation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\AttributeConfiguration;
use Symfony\Component\Serializer\Annotation\Mapping;

/**
 * @author Bertrand Seurot <b.seurot@gmail.com>
 */
class MappingTest extends TestCase
{
    public function testGetAttributesAndGroupsAndMaxDepth()
    {
        $annotation = new Mapping(['foo', 'bar'], ['read', 'write'], 2);

        $this->assertEquals(
            [new AttributeConfiguration('foo'), new AttributeConfiguration('bar')],
            $annotation->getAttributes()
        );
        $this->assertSame(['read', 'write'], $annotation->getGroups());
        $this->assertSame(2, $annotation->getMaxDepth());
    }

    public function testGetAttributesAndGroupsAndMaxDepthWithArrayInput()
    {
        $annotation = new Mapping([
            'attributes' => ['foo', 'bar'],
            'groups' => ['read', 'write'],
            'maxDepth' => 2,
        ]);

        $this->assertEquals(
            [new AttributeConfiguration('foo'), new AttributeConfiguration('bar')],
            $annotation->getAttributes()
        );
        $this->assertSame(['read', 'write'], $annotation->getGroups());
        $this->assertSame(2, $annotation->getMaxDepth());
    }

    public function testGetAttributesWithPerAttributeConfiguration()
    {
        $attributes = [
            [
                'name' => 'foo',
                'groups' => ['read', 'write'],
                'maxDepth' => 2,
                'serializedName' => 'fooBar',
            ],
            [
                'name' => 'bar',
                'maxDepth' => 3,
            ],
            [
                'name' => 'baz',
                'groups' => ['read', 'write'],
            ],
            [
                'name' => 'qux',
                'serializedName' => 'quux',
            ],
        ];

        $expectedResult = array_map(
            function ($item) {
                return new AttributeConfiguration(
                    $item['name'] ?? null,
                    $item['groups'] ?? null,
                    $item['maxDepth'] ?? null,
                    $item['serializedName'] ?? null
                );
            },
            $attributes
        );

        $annotation = new Mapping(['attributes' => $attributes]);
        $this->assertEquals($expectedResult, $annotation->getAttributes());
    }

    public function testGetAttributesAndGroupsAcceptStrings()
    {
        $annotation = new Mapping([
            'attributes' => 'foo',
            'groups' => 'read',
        ]);

        $this->assertEquals([new AttributeConfiguration('foo')], $annotation->getAttributes());
        $this->assertEquals(['read'], $annotation->getGroups());
    }

    public function testExceptionWithEmptyAttributes()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\InvalidArgumentException');
        new Mapping(['attributes' => []]);
        new Mapping(['attributes' => '']);
    }

    public function testExceptionWithUnnamedAttributes()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\InvalidArgumentException');
        new Mapping(['attributes' => ['maxDepth' => 2]]);
    }

    public function provideUselessConfigurations()
    {
        return [
            [['attributes' => 'foo']],
            [['attributes' => [
                ['name' => 'bar', 'maxDepth' => 1],
                ['name' => 'foo'],
                ],
            ]],
        ];
    }

    /**
     * @dataProvider provideUselessConfigurations
     */
    public function testExceptionIfUselessConfiguration($value)
    {
        $this->expectException('Symfony\Component\Serializer\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Attribute "foo" defined in annotation "Symfony\Component\Serializer\Annotation\Mapping" has none of the following parameters : "groups", "serializedName", "maxDepth". Defining it will so have no effect.');
        new Mapping($value);
    }

    public function testExceptionWithEmptyGroups()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Parameter "groups" of annotation "Symfony\Component\Serializer\Annotation\Mapping" must be a non-empty string or an array of non-empty strings.');
        new Mapping(['attributes' => 'foo', 'groups' => '']);
        new Mapping(['attributes' => 'foo', 'groups' => []]);
    }

    public function provideInvalidTypesMaxDepthValues()
    {
        return [
            [''],
            ['foo'],
            ['1'],
            [[1]],
            [1.5],
        ];
    }

    /**
     * @dataProvider provideInvalidTypesMaxDepthValues
     */
    public function testMaxDepthParameterType($value)
    {
        $this->expectException('\TypeError');
        new Mapping(['attributes' => 'foo', 'maxDepth' => $value]);
    }

    public function provideInvalidMaxDepthValues()
    {
        return [
            [0],
            [-1],
        ];
    }

    /**
     * @dataProvider provideInvalidMaxDepthValues
     */
    public function testNotAnIntMaxDepthParameter($value)
    {
        $this->expectException('Symfony\Component\Serializer\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Parameter "maxDepth" of annotation "Symfony\Component\Serializer\Annotation\Mapping" must be a positive integer.');
        new Mapping(['attributes' => 'foo', 'maxDepth' => $value]);
    }
}
