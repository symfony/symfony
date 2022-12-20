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

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class MapDenormalizationTest extends TestCase
{
    public function testMapOfStringToNullableObject()
    {
        $normalizedData = $this->getSerializer()->denormalize([
            'map' => [
                'assertDummyMapValue' => [
                    'value' => 'foo',
                ],
                'assertNull' => null,
            ],
        ], DummyMapOfStringToNullableObject::class);

        self::assertInstanceOf(DummyMapOfStringToNullableObject::class, $normalizedData);

        // check nullable map value
        self::assertIsArray($normalizedData->map);

        self::assertArrayHasKey('assertDummyMapValue', $normalizedData->map);
        self::assertInstanceOf(DummyValue::class, $normalizedData->map['assertDummyMapValue']);

        self::assertArrayHasKey('assertNull', $normalizedData->map);

        self::assertNull($normalizedData->map['assertNull']);
    }

    public function testMapOfStringToAbstractNullableObject()
    {
        $normalizedData = $this->getSerializer()->denormalize(
            [
                'map' => [
                    'assertNull' => null,
                ],
            ], DummyMapOfStringToNullableAbstractObject::class);

        self::assertInstanceOf(DummyMapOfStringToNullableAbstractObject::class, $normalizedData);

        self::assertIsArray($normalizedData->map);
        self::assertArrayHasKey('assertNull', $normalizedData->map);
        self::assertNull($normalizedData->map['assertNull']);
    }

    public function testMapOfStringToObject()
    {
        $normalizedData = $this->getSerializer()->denormalize(
            [
                'map' => [
                    'assertDummyMapValue' => [
                        'value' => 'foo',
                    ],
                    'assertEmptyDummyMapValue' => null,
                ],
            ], DummyMapOfStringToObject::class);

        self::assertInstanceOf(DummyMapOfStringToObject::class, $normalizedData);

        // check nullable map value
        self::assertIsArray($normalizedData->map);

        self::assertArrayHasKey('assertDummyMapValue', $normalizedData->map);
        self::assertInstanceOf(DummyValue::class, $normalizedData->map['assertDummyMapValue']);
        self::assertEquals('foo', $normalizedData->map['assertDummyMapValue']->value);

        self::assertArrayHasKey('assertEmptyDummyMapValue', $normalizedData->map);
        self::assertInstanceOf(DummyValue::class, $normalizedData->map['assertEmptyDummyMapValue']); // correct since to attribute is not nullable
        self::assertNull($normalizedData->map['assertEmptyDummyMapValue']->value);
    }

    public function testMapOfStringToAbstractObject()
    {
        $normalizedData = $this->getSerializer()->denormalize(
            [
                'map' => [
                    'assertDummyMapValue' => [
                        'type' => 'dummy',
                        'value' => 'foo',
                    ],
                ],
            ], DummyMapOfStringToNotNullableAbstractObject::class);

        self::assertInstanceOf(DummyMapOfStringToNotNullableAbstractObject::class, $normalizedData);

        // check nullable map value
        self::assertIsArray($normalizedData->map);

        self::assertArrayHasKey('assertDummyMapValue', $normalizedData->map);
        self::assertInstanceOf(DummyValue::class, $normalizedData->map['assertDummyMapValue']);
        self::assertEquals('foo', $normalizedData->map['assertDummyMapValue']->value);
    }

    public function testMapOfStringToAbstractObjectMissingTypeAttribute()
    {
        self::expectException(NotNormalizableValueException::class);
        self::expectExceptionMessage('Type property "type" not found for the abstract object "Symfony\Component\Serializer\Tests\Normalizer\AbstractDummyValue".');

        $this->getSerializer()->denormalize(
            [
                'map' => [
                    'assertEmptyDummyMapValue' => null,
                ],
            ], DummyMapOfStringToNotNullableAbstractObject::class);
    }

    public function testNullableObject()
    {
        $normalizedData = $this->getSerializer()->denormalize(
            [
                'object' => [
                    'value' => 'foo',
                ],
                'nullObject' => null,
            ], DummyNullableObjectValue::class);

        self::assertInstanceOf(DummyNullableObjectValue::class, $normalizedData);

        self::assertInstanceOf(DummyValue::class, $normalizedData->object);
        self::assertEquals('foo', $normalizedData->object->value);

        self::assertNull($normalizedData->nullObject);
    }

    public function testNotNullableObject()
    {
        $normalizedData = $this->getSerializer()->denormalize(
            [
                'object' => [
                    'value' => 'foo',
                ],
                'nullObject' => null,
            ], DummyNotNullableObjectValue::class);

        self::assertInstanceOf(DummyNotNullableObjectValue::class, $normalizedData);

        self::assertInstanceOf(DummyValue::class, $normalizedData->object);
        self::assertEquals('foo', $normalizedData->object->value);

        self::assertInstanceOf(DummyValue::class, $normalizedData->nullObject);
        self::assertNull($normalizedData->nullObject->value);
    }

    public function testNullableAbstractObject()
    {
        $normalizedData = $this->getSerializer()->denormalize(
            [
                'object' => [
                    'type' => 'another-dummy',
                    'value' => 'foo',
                ],
                'nullObject' => null,
            ], DummyNullableAbstractObjectValue::class);

        self::assertInstanceOf(DummyNullableAbstractObjectValue::class, $normalizedData);

        self::assertInstanceOf(AnotherDummyValue::class, $normalizedData->object);
        self::assertEquals('foo', $normalizedData->object->value);

        self::assertNull($normalizedData->nullObject);
    }

    private function getSerializer()
    {
        $loaderMock = new class() implements ClassMetadataFactoryInterface {
            public function getMetadataFor($value): ClassMetadataInterface
            {
                if (AbstractDummyValue::class === $value) {
                    return new ClassMetadata(
                        AbstractDummyValue::class,
                        new ClassDiscriminatorMapping('type', [
                            'dummy' => DummyValue::class,
                            'another-dummy' => AnotherDummyValue::class,
                        ])
                    );
                }

                throw new InvalidArgumentException();
            }

            public function hasMetadataFor($value): bool
            {
                return AbstractDummyValue::class === $value;
            }
        };

        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new ObjectNormalizer($factory, null, null, new PhpDocExtractor(), new ClassDiscriminatorFromClassMetadata($loaderMock));
        $serializer = new Serializer([$normalizer, new ArrayDenormalizer()]);
        $normalizer->setSerializer($serializer);

        return $serializer;
    }
}

abstract class AbstractDummyValue
{
    public $value;
}

class DummyValue extends AbstractDummyValue
{
}

class AnotherDummyValue extends AbstractDummyValue
{
}

class DummyNotNullableObjectValue
{
    /**
     * @var DummyValue
     */
    public $object;

    /**
     * @var DummyValue
     */
    public $nullObject;
}

class DummyNullableObjectValue
{
    /**
     * @var DummyValue|null
     */
    public $object;

    /**
     * @var DummyValue|null
     */
    public $nullObject;
}

class DummyNullableAbstractObjectValue
{
    /**
     * @var AbstractDummyValue|null
     */
    public $object;

    /**
     * @var AbstractDummyValue|null
     */
    public $nullObject;
}

class DummyMapOfStringToNullableObject
{
    /**
     * @var array<string,DummyValue|null>
     */
    public $map;
}

class DummyMapOfStringToObject
{
    /**
     * @var array<string,DummyValue>
     */
    public $map;
}

class DummyMapOfStringToNullableAbstractObject
{
    /**
     * @var array<string,AbstractDummyValue|null>
     */
    public $map;
}

class DummyMapOfStringToNotNullableAbstractObject
{
    /**
     * @var array<string,AbstractDummyValue>
     */
    public $map;
}
