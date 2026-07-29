<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Tests\Fixtures\ArrayOfScalarUnionTypeContainerDummy;
use Symfony\Component\Serializer\Tests\Fixtures\ArrayOfUnionTypeContainerDummy;
use Symfony\Component\Serializer\Tests\Fixtures\ArrayOfUnknownClassUnionTypeContainerDummy;
use Symfony\Component\Serializer\Tests\Fixtures\NullableArrayOfUnionTypeContainerDummy;
use Symfony\Component\Serializer\Tests\Fixtures\StringKeyedArrayOfUnionTypeContainerDummy;
use Symfony\Component\Serializer\Tests\Fixtures\UnionTypeBarDummy;
use Symfony\Component\Serializer\Tests\Fixtures\UnionTypeContainerDummy;
use Symfony\Component\Serializer\Tests\Fixtures\UnionTypeFooDummy;

class DenormalizeArrayOfUnionTypesTest extends TestCase
{
    private DenormalizerInterface $denormalizer;

    protected function setUp(): void
    {
        $propertyTypeExtractor = new PropertyInfoExtractor(
            typeExtractors: [new PhpDocExtractor(), new ReflectionExtractor()]
        );

        $this->denormalizer = new Serializer([
            new ObjectNormalizer(null, null, null, $propertyTypeExtractor),
            new ArrayDenormalizer(),
        ]);
    }

    public function testDenormalizeDirectUnionType()
    {
        $result = $this->denormalizer->denormalize(['fooOrBar' => ['bar' => true]], UnionTypeContainerDummy::class);

        $this->assertInstanceOf(UnionTypeContainerDummy::class, $result);
        $this->assertInstanceOf(UnionTypeBarDummy::class, $result->fooOrBar);
        $this->assertTrue($result->fooOrBar->bar);
    }

    public function testDenormalizeArrayOfUnionTypes()
    {
        $data = ['items' => [['foo' => true], ['bar' => true], ['foo' => false]]];

        $result = $this->denormalizer->denormalize($data, ArrayOfUnionTypeContainerDummy::class);

        $this->assertInstanceOf(ArrayOfUnionTypeContainerDummy::class, $result);
        $this->assertCount(3, $result->items);
        $this->assertInstanceOf(UnionTypeFooDummy::class, $result->items[0]);
        $this->assertTrue($result->items[0]->foo);
        $this->assertInstanceOf(UnionTypeBarDummy::class, $result->items[1]);
        $this->assertTrue($result->items[1]->bar);
        $this->assertInstanceOf(UnionTypeFooDummy::class, $result->items[2]);
        $this->assertFalse($result->items[2]->foo);
    }

    public function testDenormalizeArrayOfNullableUnionTypes()
    {
        $data = ['items' => [['foo' => true], null, ['bar' => false]]];

        $result = $this->denormalizer->denormalize($data, NullableArrayOfUnionTypeContainerDummy::class);

        $this->assertInstanceOf(UnionTypeFooDummy::class, $result->items[0]);
        $this->assertNull($result->items[1]);
        $this->assertInstanceOf(UnionTypeBarDummy::class, $result->items[2]);
        $this->assertFalse($result->items[2]->bar);
    }

    public function testDenormalizeStringKeyedArrayOfUnionTypes()
    {
        $data = ['items' => ['first' => ['foo' => true], 'second' => ['bar' => true]]];

        $result = $this->denormalizer->denormalize($data, StringKeyedArrayOfUnionTypeContainerDummy::class);

        $this->assertInstanceOf(StringKeyedArrayOfUnionTypeContainerDummy::class, $result);
        $this->assertCount(2, $result->items);
        $this->assertInstanceOf(UnionTypeFooDummy::class, $result->items['first']);
        $this->assertTrue($result->items['first']->foo);
        $this->assertInstanceOf(UnionTypeBarDummy::class, $result->items['second']);
        $this->assertTrue($result->items['second']->bar);
    }

    public function testDenormalizeStringKeyedArrayWithInvalidKeyTypeThrows()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('The type of the key "0" must be "string" ("int" given).');

        $this->denormalizer->denormalize(['items' => [0 => ['foo' => true]]], StringKeyedArrayOfUnionTypeContainerDummy::class);
    }

    public function testDenormalizeArrayOfScalarUnionTypes()
    {
        $result = $this->denormalizer->denormalize(['items' => [1, 'two']], ArrayOfScalarUnionTypeContainerDummy::class);

        $this->assertSame([1, 'two'], $result->items);
    }

    public function testDenormalizeArrayOfScalarUnionTypesRejectsUnmatchedElements()
    {
        $this->expectException(NotNormalizableValueException::class);

        $this->denormalizer->denormalize(['items' => [1.5]], ArrayOfScalarUnionTypeContainerDummy::class);
    }

    public function testDenormalizeArrayOfUnknownClassUnionTypesRejectsElements()
    {
        $this->expectException(NotNormalizableValueException::class);

        $this->denormalizer->denormalize(['items' => [['foo' => true]]], ArrayOfUnknownClassUnionTypeContainerDummy::class);
    }

    public function testDenormalizeNonArrayDataThrows()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage(\sprintf('The type of the "items" attribute for class "%s" must be one of "array" ("string" given).', ArrayOfUnionTypeContainerDummy::class));

        $this->denormalizer->denormalize(['items' => 'not an array'], ArrayOfUnionTypeContainerDummy::class);
    }
}
