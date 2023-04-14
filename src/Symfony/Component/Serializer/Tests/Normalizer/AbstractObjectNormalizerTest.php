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
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Annotation\SerializedPath;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummyFirstChild;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummySecondChild;
use Symfony\Component\Serializer\Tests\Fixtures\DummySecondChildQuux;
use Symfony\Component\Serializer\Tests\Normalizer\Features\ObjectDummyWithContextAttribute;

class AbstractObjectNormalizerTest extends TestCase
{
    public function testDenormalize()
    {
        $normalizer = new AbstractObjectNormalizerDummy();
        $normalizedData = $normalizer->denormalize(['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'], Dummy::class);

        $this->assertSame('foo', $normalizedData->foo);
        $this->assertNull($normalizedData->bar);
        $this->assertSame('baz', $normalizedData->baz);
    }

    public function testInstantiateObjectDenormalizer()
    {
        $data = ['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'];
        $class = Dummy::class;
        $context = [];

        $normalizer = new AbstractObjectNormalizerDummy();

        $this->assertInstanceOf(Dummy::class, $normalizer->instantiateObject($data, $class, $context, new \ReflectionClass($class), []));
    }

    public function testDenormalizeWithExtraAttribute()
    {
        $this->expectException(ExtraAttributesException::class);
        $this->expectExceptionMessage('Extra attributes are not allowed ("fooFoo" is unknown).');
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new AbstractObjectNormalizerDummy($factory);
        $normalizer->denormalize(
            ['fooFoo' => 'foo'],
            Dummy::class,
            'any',
            ['allow_extra_attributes' => false]
        );
    }

    public function testDenormalizeWithExtraAttributes()
    {
        $this->expectException(ExtraAttributesException::class);
        $this->expectExceptionMessage('Extra attributes are not allowed ("fooFoo", "fooBar" are unknown).');
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new AbstractObjectNormalizerDummy($factory);
        $normalizer->denormalize(
            ['fooFoo' => 'foo', 'fooBar' => 'bar'],
            Dummy::class,
            'any',
            ['allow_extra_attributes' => false]
        );
    }

    public function testDenormalizeWithExtraAttributesAndNoGroupsWithMetadataFactory()
    {
        $this->expectException(ExtraAttributesException::class);
        $this->expectExceptionMessage('Extra attributes are not allowed ("fooFoo", "fooBar" are unknown).');
        $normalizer = new AbstractObjectNormalizerWithMetadata();
        $normalizer->denormalize(
            ['fooFoo' => 'foo', 'fooBar' => 'bar', 'bar' => 'bar'],
            Dummy::class,
            'any',
            ['allow_extra_attributes' => false]
        );
    }

    public function testDenormalizeWithDuplicateNestedAttributes()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Duplicate serialized path: "one,two,three" used for properties "foo" and "bar".');
        $normalizer = new AbstractObjectNormalizerWithMetadata();
        $normalizer->denormalize([], DuplicateValueNestedDummy::class, 'any');
    }

    public function testDenormalizeWithNestedAttributesWithoutMetadata()
    {
        $normalizer = new AbstractObjectNormalizerDummy();
        $data = [
            'one' => [
                'two' => [
                    'three' => 'foo',
                ],
                'four' => 'quux',
            ],
            'foo' => 'notfoo',
            'baz' => 'baz',
        ];
        $test = $normalizer->denormalize($data, NestedDummy::class, 'any');
        $this->assertSame('notfoo', $test->foo);
        $this->assertSame('baz', $test->baz);
        $this->assertNull($test->notfoo);
    }

    public function testDenormalizeWithNestedAttributes()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadata();
        $data = [
            'one' => [
                'two' => [
                    'three' => 'foo',
                ],
                'four' => 'quux',
            ],
            'foo' => 'notfoo',
            'baz' => 'baz',
        ];
        $test = $normalizer->denormalize($data, NestedDummy::class, 'any');
        $this->assertSame('baz', $test->baz);
        $this->assertSame('foo', $test->foo);
        $this->assertSame('quux', $test->quux);
        $this->assertSame('notfoo', $test->notfoo);
    }

    public function testDenormalizeWithNestedAttributesDuplicateKeys()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Duplicate values for key "quux" found. One value is set via the SerializedPath annotation: "one->four", the other one is set via the SerializedName annotation: "notquux".');
        $normalizer = new AbstractObjectNormalizerWithMetadata();
        $data = [
            'one' => [
                'four' => 'quux',
            ],
            'quux' => 'notquux',
        ];
        $normalizer->denormalize($data, DuplicateKeyNestedDummy::class, 'any');
    }

    public function testNormalizeWithNestedAttributesMixingArrayTypes()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The element you are trying to set is already populated: "[one][two]"');
        $foobar = new AlreadyPopulatedNestedDummy();
        $foobar->foo = 'foo';
        $foobar->bar = 'bar';
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));
        $normalizer->normalize($foobar, 'any');
    }

    public function testNormalizeWithNestedAttributesElementAlreadySet()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The element you are trying to set is already populated: "[one][two][three]"');
        $foobar = new DuplicateValueNestedDummy();
        $foobar->foo = 'foo';
        $foobar->bar = 'bar';
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));
        $normalizer->normalize($foobar, 'any');
    }

    public function testNormalizeWithNestedAttributes()
    {
        $foobar = new NestedDummy();
        $foobar->foo = 'foo';
        $foobar->quux = 'quux';
        $foobar->baz = 'baz';
        $foobar->notfoo = 'notfoo';
        $data = [
            'one' => [
                'two' => [
                    'three' => 'foo',
                ],
                'four' => 'quux',
            ],
            'foo' => 'notfoo',
            'baz' => 'baz',
        ];
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));
        $test = $normalizer->normalize($foobar, 'any');
        $this->assertSame($data, $test);
    }

    public function testNormalizeWithNestedAttributesWithoutMetadata()
    {
        $foobar = new NestedDummy();
        $foobar->foo = 'foo';
        $foobar->quux = 'quux';
        $foobar->baz = 'baz';
        $foobar->notfoo = 'notfoo';
        $data = [
            'foo' => 'foo',
            'quux' => 'quux',
            'notfoo' => 'notfoo',
            'baz' => 'baz',
        ];
        $normalizer = new ObjectNormalizer();
        $test = $normalizer->normalize($foobar, 'any');
        $this->assertSame($data, $test);
    }

    public function testDenormalizeCollectionDecodedFromXmlWithOneChild()
    {
        $denormalizer = $this->getDenormalizerForDummyCollection();

        $dummyCollection = $denormalizer->denormalize(
            [
                'children' => [
                    'bar' => 'first',
                ],
            ],
            DummyCollection::class,
            'xml'
        );

        $this->assertInstanceOf(DummyCollection::class, $dummyCollection);
        $this->assertIsArray($dummyCollection->children);
        $this->assertCount(1, $dummyCollection->children);
        $this->assertInstanceOf(DummyChild::class, $dummyCollection->children[0]);
    }

    public function testDenormalizeCollectionDecodedFromXmlWithTwoChildren()
    {
        $denormalizer = $this->getDenormalizerForDummyCollection();

        $dummyCollection = $denormalizer->denormalize(
            [
                'children' => [
                    ['bar' => 'first'],
                    ['bar' => 'second'],
                ],
            ],
            DummyCollection::class,
            'xml'
        );

        $this->assertInstanceOf(DummyCollection::class, $dummyCollection);
        $this->assertIsArray($dummyCollection->children);
        $this->assertCount(2, $dummyCollection->children);
        $this->assertInstanceOf(DummyChild::class, $dummyCollection->children[0]);
        $this->assertInstanceOf(DummyChild::class, $dummyCollection->children[1]);
    }

    private function getDenormalizerForDummyCollection()
    {
        $extractor = $this->createMock(PhpDocExtractor::class);
        $extractor->method('getTypes')
            ->will($this->onConsecutiveCalls(
                [new Type('array', false, null, true, new Type('int'), new Type('object', false, DummyChild::class))],
                null
            ));

        $denormalizer = new AbstractObjectNormalizerCollectionDummy(null, null, $extractor);
        $arrayDenormalizer = new ArrayDenormalizerDummy();
        $serializer = new SerializerCollectionDummy([$arrayDenormalizer, $denormalizer]);
        $arrayDenormalizer->setSerializer($serializer);
        $denormalizer->setSerializer($serializer);

        return $denormalizer;
    }

    public function testDenormalizeStringCollectionDecodedFromXmlWithOneChild()
    {
        $denormalizer = $this->getDenormalizerForStringCollection();

        // if an xml-node can have children which should be deserialized as string[]
        // and only one child exists
        $stringCollection = $denormalizer->denormalize(['children' => 'foo'], StringCollection::class, 'xml');

        $this->assertInstanceOf(StringCollection::class, $stringCollection);
        $this->assertIsArray($stringCollection->children);
        $this->assertCount(1, $stringCollection->children);
        $this->assertEquals('foo', $stringCollection->children[0]);
    }

    public function testDenormalizeStringCollectionDecodedFromXmlWithTwoChildren()
    {
        $denormalizer = $this->getDenormalizerForStringCollection();

        // if an xml-node can have children which should be deserialized as string[]
        // and only one child exists
        $stringCollection = $denormalizer->denormalize(['children' => ['foo', 'bar']], StringCollection::class, 'xml');

        $this->assertInstanceOf(StringCollection::class, $stringCollection);
        $this->assertIsArray($stringCollection->children);
        $this->assertCount(2, $stringCollection->children);
        $this->assertEquals('foo', $stringCollection->children[0]);
        $this->assertEquals('bar', $stringCollection->children[1]);
    }

    public function testDenormalizeNotSerializableObjectToPopulate()
    {
        $normalizer = new AbstractObjectNormalizerDummy();
        $normalizedData = $normalizer->denormalize(['foo' => 'foo'], Dummy::class, null, [AbstractObjectNormalizer::OBJECT_TO_POPULATE => new NotSerializable()]);

        $this->assertSame('foo', $normalizedData->foo);
    }

    private function getDenormalizerForStringCollection()
    {
        $extractor = $this->createMock(PhpDocExtractor::class);
        $extractor->method('getTypes')
            ->will($this->onConsecutiveCalls(
                [new Type('array', false, null, true, new Type('int'), new Type('string'))],
                null
            ));

        $denormalizer = new AbstractObjectNormalizerCollectionDummy(null, null, $extractor);
        $arrayDenormalizer = new ArrayDenormalizerDummy();
        $serializer = new SerializerCollectionDummy([$arrayDenormalizer, $denormalizer]);
        $arrayDenormalizer->setSerializer($serializer);
        $denormalizer->setSerializer($serializer);

        return $denormalizer;
    }

    public function testDenormalizeWithDiscriminatorMapUsesCorrectClassname()
    {
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $loaderMock = new class() implements ClassMetadataFactoryInterface {
            public function getMetadataFor($value): ClassMetadataInterface
            {
                if (AbstractDummy::class === $value) {
                    return new ClassMetadata(
                        AbstractDummy::class,
                        new ClassDiscriminatorMapping('type', [
                            'first' => AbstractDummyFirstChild::class,
                            'second' => AbstractDummySecondChild::class,
                        ])
                    );
                }

                throw new InvalidArgumentException();
            }

            public function hasMetadataFor($value): bool
            {
                return AbstractDummy::class === $value;
            }
        };

        $discriminatorResolver = new ClassDiscriminatorFromClassMetadata($loaderMock);
        $normalizer = new AbstractObjectNormalizerDummy($factory, null, new PhpDocExtractor(), $discriminatorResolver);
        $serializer = new Serializer([$normalizer]);
        $normalizer->setSerializer($serializer);
        $normalizedData = $normalizer->denormalize(['foo' => 'foo', 'baz' => 'baz', 'quux' => ['value' => 'quux'], 'type' => 'second'], AbstractDummy::class);

        $this->assertInstanceOf(DummySecondChildQuux::class, $normalizedData->quux);
    }

    public function testDenormalizeWithNestedDiscriminatorMap()
    {
        $classDiscriminatorResolver = new class() implements ClassDiscriminatorResolverInterface {
            public function getMappingForClass(string $class): ?ClassDiscriminatorMapping
            {
                return match ($class) {
                    AbstractDummy::class => new ClassDiscriminatorMapping('type', [
                        'foo' => AbstractDummyFirstChild::class,
                    ]),
                    AbstractDummyFirstChild::class => new ClassDiscriminatorMapping('nested_type', [
                        'bar' => AbstractDummySecondChild::class,
                    ]),
                    default => null,
                };
            }

            public function getMappingForMappedObject($object): ?ClassDiscriminatorMapping
            {
                return null;
            }

            public function getTypeForMappedObject($object): ?string
            {
                return null;
            }
        };

        $normalizer = new AbstractObjectNormalizerDummy(null, null, null, $classDiscriminatorResolver);

        $denormalizedData = $normalizer->denormalize(['type' => 'foo', 'nested_type' => 'bar'], AbstractDummy::class);

        $this->assertInstanceOf(AbstractDummySecondChild::class, $denormalizedData);
    }

    public function testDenormalizeBasicTypePropertiesFromXml()
    {
        $denormalizer = $this->getDenormalizerForObjectWithBasicProperties();

        // bool
        $objectWithBooleanProperties = $denormalizer->denormalize(
            [
                'boolTrue1' => 'true',
                'boolFalse1' => 'false',
                'boolTrue2' => '1',
                'boolFalse2' => '0',
                'int1' => '4711',
                'int2' => '-4711',
                'float1' => '123.456',
                'float2' => '-1.2344e56',
                'float3' => '45E-6',
                'floatNaN' => 'NaN',
                'floatInf' => 'INF',
                'floatNegInf' => '-INF',
            ],
            ObjectWithBasicProperties::class,
            'xml'
        );

        $this->assertInstanceOf(ObjectWithBasicProperties::class, $objectWithBooleanProperties);

        // Bool Properties
        $this->assertTrue($objectWithBooleanProperties->boolTrue1);
        $this->assertFalse($objectWithBooleanProperties->boolFalse1);
        $this->assertTrue($objectWithBooleanProperties->boolTrue2);
        $this->assertFalse($objectWithBooleanProperties->boolFalse2);

        // Integer Properties
        $this->assertEquals(4711, $objectWithBooleanProperties->int1);
        $this->assertEquals(-4711, $objectWithBooleanProperties->int2);

        // Float Properties
        $this->assertEqualsWithDelta(123.456, $objectWithBooleanProperties->float1, 0.01);
        $this->assertEqualsWithDelta(-1.2344e56, $objectWithBooleanProperties->float2, 1);
        $this->assertEqualsWithDelta(45E-6, $objectWithBooleanProperties->float3, 1);
        $this->assertNan($objectWithBooleanProperties->floatNaN);
        $this->assertInfinite($objectWithBooleanProperties->floatInf);
        $this->assertEquals(-\INF, $objectWithBooleanProperties->floatNegInf);
    }

    private function getDenormalizerForObjectWithBasicProperties()
    {
        $extractor = $this->createMock(PhpDocExtractor::class);
        $extractor->method('getTypes')
            ->will($this->onConsecutiveCalls(
                [new Type('bool')],
                [new Type('bool')],
                [new Type('bool')],
                [new Type('bool')],
                [new Type('int')],
                [new Type('int')],
                [new Type('float')],
                [new Type('float')],
                [new Type('float')],
                [new Type('float')],
                [new Type('float')],
                [new Type('float')]
            ));

        $denormalizer = new AbstractObjectNormalizerCollectionDummy(null, null, $extractor);
        $arrayDenormalizer = new ArrayDenormalizerDummy();
        $serializer = new SerializerCollectionDummy([$arrayDenormalizer, $denormalizer]);
        $arrayDenormalizer->setSerializer($serializer);
        $denormalizer->setSerializer($serializer);

        return $denormalizer;
    }

    /**
     * Test that additional attributes throw an exception if no metadata factory is specified.
     */
    public function testExtraAttributesException()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A class metadata factory must be provided in the constructor when setting "allow_extra_attributes" to false.');
        $normalizer = new ObjectNormalizer();

        $normalizer->denormalize([], \stdClass::class, 'xml', [
            'allow_extra_attributes' => false,
        ]);
    }

    public function testNormalizeEmptyObject()
    {
        $normalizer = new AbstractObjectNormalizerDummy();

        // This results in objects turning into arrays in some encoders
        $normalizedData = $normalizer->normalize(new EmptyDummy());
        $this->assertEquals([], $normalizedData);

        $normalizedData = $normalizer->normalize(new EmptyDummy(), 'any', ['preserve_empty_objects' => true]);
        $this->assertEquals(new \ArrayObject(), $normalizedData);
    }

    public function testDenormalizeRecursiveWithObjectAttributeWithStringValue()
    {
        $extractor = new ReflectionExtractor();
        $normalizer = new ObjectNormalizer(null, null, null, $extractor);
        $serializer = new Serializer([$normalizer]);

        $obj = $serializer->denormalize(['inner' => 'foo'], ObjectOuter::class);

        $this->assertInstanceOf(ObjectInner::class, $obj->getInner());
    }

    public function testDenormalizeUsesContextAttributeForPropertiesInConstructorWithSeralizedName()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory), null, $extractor);
        $serializer = new Serializer([new DateTimeNormalizer([DateTimeNormalizer::FORMAT_KEY => 'd-m-Y']), $normalizer]);

        /** @var ObjectDummyWithContextAttribute $obj */
        $obj = $serializer->denormalize(['property_with_serialized_name' => '01-02-2022', 'propertyWithoutSerializedName' => '01-02-2022'], ObjectDummyWithContextAttribute::class);

        $this->assertSame($obj->propertyWithSerializedName->format('Y-m-d'), $obj->propertyWithoutSerializedName->format('Y-m-d'));
    }

    public function testNormalizeUsesContextAttributeForPropertiesInConstructorWithSerializedPath()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory), null, $extractor);
        $serializer = new Serializer([new DateTimeNormalizer(), $normalizer]);

        $obj = new ObjectDummyWithContextAttributeAndSerializedPath(new \DateTimeImmutable('22-02-2023'));

        $data = $serializer->normalize($obj);

        $this->assertSame(['property' => ['with_path' => '02-22-2023']], $data);
    }

    public function testNormalizeUsesContextAttributeForProperties()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory), null, $extractor);
        $serializer = new Serializer([$normalizer]);

        $obj = new ObjectDummyWithContextAttributeSkipNullValues();

        $data = $serializer->normalize($obj);

        $this->assertSame(['propertyWithoutNullSkipNullValues' => 'foo'], $data);
    }
}

class AbstractObjectNormalizerDummy extends AbstractObjectNormalizer
{
    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    protected function extractAttributes(object $object, string $format = null, array $context = []): array
    {
        return [];
    }

    protected function getAttributeValue(object $object, string $attribute, string $format = null, array $context = []): mixed
    {
    }

    protected function setAttributeValue(object $object, string $attribute, $value, string $format = null, array $context = [])
    {
        $object->$attribute = $value;
    }

    protected function isAllowedAttribute($classOrObject, string $attribute, string $format = null, array $context = []): bool
    {
        return \in_array($attribute, ['foo', 'baz', 'quux', 'value']);
    }

    public function instantiateObject(array &$data, string $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes, string $format = null): object
    {
        return parent::instantiateObject($data, $class, $context, $reflectionClass, $allowedAttributes, $format);
    }
}

class Dummy
{
    public $foo;
    public $bar;
    public $baz;
}

class EmptyDummy
{
}

class AlreadyPopulatedNestedDummy
{
    /**
     * @SerializedPath("[one][two][three]")
     */
    public $foo;

    /**
     * @SerializedPath("[one][two]")
     */
    public $bar;
}

class DuplicateValueNestedDummy
{
    /**
     * @SerializedPath("[one][two][three]")
     */
    public $foo;

    /**
     * @SerializedPath("[one][two][three]")
     */
    public $bar;

    public $baz;
}

class NestedDummy
{
    /**
     * @SerializedPath("[one][two][three]")
     */
    public $foo;

    /**
     * @SerializedPath("[one][four]")
     */
    public $quux;

    /**
     * @SerializedPath("[foo]")
     */
    public $notfoo;

    public $baz;
}

class DuplicateKeyNestedDummy
{
    /**
     * @SerializedPath("[one][four]")
     */
    public $quux;

    /**
     * @SerializedName("quux")
     */
    public $notquux;
}

class ObjectDummyWithContextAttributeAndSerializedPath
{
    public function __construct(
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'm-d-Y'])]
        #[SerializedPath('[property][with_path]')]
        public \DateTimeImmutable $propertyWithPath,
    ) {
    }
}

class ObjectDummyWithContextAttributeSkipNullValues
{
    #[Context([AbstractObjectNormalizer::SKIP_NULL_VALUES => true])]
    public ?string $propertyWithoutNullSkipNullValues = 'foo';

    #[Context([AbstractObjectNormalizer::SKIP_NULL_VALUES => true])]
    public ?string $propertyWithNullSkipNullValues = null;
}

class AbstractObjectNormalizerWithMetadata extends AbstractObjectNormalizer
{
    public function __construct()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        parent::__construct($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    protected function extractAttributes(object $object, string $format = null, array $context = []): array
    {
    }

    protected function getAttributeValue(object $object, string $attribute, string $format = null, array $context = []): mixed
    {
    }

    protected function setAttributeValue(object $object, string $attribute, $value, string $format = null, array $context = [])
    {
        $object->$attribute = $value;
    }
}

class ObjectWithBasicProperties
{
    /** @var bool */
    public $boolTrue1;

    /** @var bool */
    public $boolFalse1;

    /** @var bool */
    public $boolTrue2;

    /** @var bool */
    public $boolFalse2;

    /** @var int */
    public $int1;

    /** @var int */
    public $int2;

    /** @var float */
    public $float1;

    /** @var float */
    public $float2;

    /** @var float */
    public $float3;

    /** @var float */
    public $floatNaN;

    /** @var float */
    public $floatInf;

    /** @var float */
    public $floatNegInf;
}

class StringCollection
{
    /** @var string[] */
    public $children;
}

class DummyCollection
{
    /** @var DummyChild[] */
    public $children;
}

class DummyChild
{
    public $bar;
}

class SerializerCollectionDummy implements SerializerInterface, DenormalizerInterface
{
    private $normalizers;

    /**
     * @param DenormalizerInterface[] $normalizers
     */
    public function __construct(array $normalizers)
    {
        $this->normalizers = $normalizers;
    }

    public function serialize($data, string $format, array $context = []): string
    {
    }

    public function deserialize($data, string $type, string $format, array $context = []): mixed
    {
    }

    public function denormalize($data, string $type, string $format = null, array $context = []): mixed
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer instanceof DenormalizerInterface && $normalizer->supportsDenormalization($data, $type, $format, $context)) {
                return $normalizer->denormalize($data, $type, $format, $context);
            }
        }

        return null;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    public function supportsDenormalization($data, string $type, string $format = null, array $context = []): bool
    {
        return true;
    }
}

class AbstractObjectNormalizerCollectionDummy extends AbstractObjectNormalizer
{
    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    protected function extractAttributes(object $object, string $format = null, array $context = []): array
    {
    }

    protected function getAttributeValue(object $object, string $attribute, string $format = null, array $context = []): mixed
    {
    }

    protected function setAttributeValue(object $object, string $attribute, $value, string $format = null, array $context = [])
    {
        $object->$attribute = $value;
    }

    protected function isAllowedAttribute($classOrObject, string $attribute, string $format = null, array $context = []): bool
    {
        return true;
    }

    public function instantiateObject(array &$data, string $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes, string $format = null): object
    {
        return parent::instantiateObject($data, $class, $context, $reflectionClass, $allowedAttributes, $format);
    }

    public function serialize($data, string $format, array $context = [])
    {
    }

    public function deserialize($data, string $type, string $format, array $context = [])
    {
    }
}

class ArrayDenormalizerDummy implements DenormalizerInterface, SerializerAwareInterface
{
    /**
     * @var SerializerInterface|DenormalizerInterface
     */
    private $serializer;

    /**
     * @throws NotNormalizableValueException
     */
    public function denormalize($data, string $type, string $format = null, array $context = []): mixed
    {
        $serializer = $this->serializer;
        $type = substr($type, 0, -2);

        foreach ($data as $key => $value) {
            $data[$key] = $serializer->denormalize($value, $type, $format, $context);
        }

        return $data;
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->serializer->getSupportedTypes($format);
    }

    public function supportsDenormalization($data, string $type, string $format = null, array $context = []): bool
    {
        return str_ends_with($type, '[]')
            && $this->serializer->supportsDenormalization($data, substr($type, 0, -2), $format, $context);
    }

    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }
}

class NotSerializable
{
    public function __sleep(): array
    {
        throw new \Error('not serializable');
    }
}
