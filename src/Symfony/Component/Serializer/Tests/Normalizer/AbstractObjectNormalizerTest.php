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

use Doctrine\Persistence\Proxy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\DiscriminatorMap;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;
use Symfony\Component\Serializer\Debug\TraceableSerializer;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AbstractDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AbstractDummyFirstChild;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AbstractDummySecondChild;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\SerializedNameDummy;
use Symfony\Component\Serializer\Tests\Fixtures\DummyFirstChildQuux;
use Symfony\Component\Serializer\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Serializer\Tests\Fixtures\DummyMessageNumberFour;
use Symfony\Component\Serializer\Tests\Fixtures\DummySecondChildQuux;
use Symfony\Component\Serializer\Tests\Fixtures\DummyString;
use Symfony\Component\Serializer\Tests\Fixtures\DummyWithNotNormalizable;
use Symfony\Component\Serializer\Tests\Fixtures\DummyWithObjectOrBool;
use Symfony\Component\Serializer\Tests\Fixtures\DummyWithObjectOrNull;
use Symfony\Component\Serializer\Tests\Fixtures\DummyWithStringObject;
use Symfony\Component\Serializer\Tests\Fixtures\IntegerBackedEnumDummy;
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
        $factory = new ClassMetadataFactory(new AttributeLoader());
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
        $factory = new ClassMetadataFactory(new AttributeLoader());
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

    public function testDenormalizePlainObject()
    {
        $extractor = new PhpDocExtractor();
        $normalizer = new ObjectNormalizer(null, null, null, $extractor);
        $dummy = $normalizer->denormalize(['plainObject' => (object) ['foo' => 'bar']], DummyWithPlainObject::class);

        $this->assertInstanceOf(DummyWithPlainObject::class, $dummy);
        $this->assertInstanceOf(\stdClass::class, $dummy->plainObject);
        $this->assertSame('bar', $dummy->plainObject->foo);
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

    public function testDenormalizeWithSnakeCaseNestedAttributes()
    {
        $factory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($factory, new CamelCaseToSnakeCaseNameConverter());
        $data = [
            'one' => [
                'two_three' => 'fooBar',
            ],
        ];
        $test = $normalizer->denormalize($data, SnakeCaseNestedDummy::class, 'any');
        $this->assertSame('fooBar', $test->fooBar);
    }

    public function testNormalizeWithSnakeCaseNestedAttributes()
    {
        $factory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($factory, new CamelCaseToSnakeCaseNameConverter());
        $dummy = new SnakeCaseNestedDummy();
        $dummy->fooBar = 'fooBar';
        $test = $normalizer->normalize($dummy, 'any');
        $this->assertSame(['one' => ['two_three' => 'fooBar']], $test);
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
        $this->expectExceptionMessage('Duplicate values for key "quux" found. One value is set via the SerializedPath attribute: "one->four", the other one is set via the SerializedName attribute: "notquux".');
        $normalizer = new AbstractObjectNormalizerWithMetadata();
        $data = [
            'one' => [
                'four' => 'quux',
            ],
            'quux' => 'notquux',
        ];
        $normalizer->denormalize($data, DuplicateKeyNestedDummy::class, 'any');
    }

    public function testDenormalizePrefersSerializedNameOverRawPropertyName()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadata();

        $object = $normalizer->denormalize(['subproject' => 'from raw key', 'subproject_id' => 'from serialized name'], SerializedNameDuplicateRawKeyDummy::class, 'any');
        $this->assertSame('from serialized name', $object->subproject);

        $object = $normalizer->denormalize(['subproject_id' => 'from serialized name', 'subproject' => 'from raw key'], SerializedNameDuplicateRawKeyDummy::class, 'any');
        $this->assertSame('from serialized name', $object->subproject);
    }

    public function testDenormalizePrefersSerializedNameOverRawPropertyNameInConstructor()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadata();

        $object = $normalizer->denormalize(['subproject' => 'from raw key', 'subproject_id' => 'from serialized name'], SerializedNameDuplicateRawKeyConstructorDummy::class, 'any');
        $this->assertSame('from serialized name', $object->subproject);

        $object = $normalizer->denormalize(['subproject_id' => 'from serialized name', 'subproject' => 'from raw key'], SerializedNameDuplicateRawKeyConstructorDummy::class, 'any');
        $this->assertSame('from serialized name', $object->subproject);
    }

    public function testDenormalizeReportsRawPropertyNameAsExtraAttribute()
    {
        $this->expectException(ExtraAttributesException::class);
        $this->expectExceptionMessage('Extra attributes are not allowed ("subproject" is unknown).');

        $normalizer = new AbstractObjectNormalizerWithMetadata();
        $normalizer->denormalize([
            'subproject' => 'from raw key',
            'subproject_id' => 'from serialized name',
        ], SerializedNameDuplicateRawKeyDummy::class, 'any', [AbstractObjectNormalizer::ALLOW_EXTRA_ATTRIBUTES => false]);
    }

    public function testDenormalizeWithNestedAttributesInConstructor()
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
        $test = $normalizer->denormalize($data, NestedDummyWithConstructor::class, 'any');
        $this->assertSame('foo', $test->foo);
        $this->assertSame('quux', $test->quux);
        $this->assertSame('notfoo', $test->notfoo);
        $this->assertSame('baz', $test->baz);
    }

    public function testDenormalizeWithNestedAttributesInConstructorAndDiscriminatorMap()
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

        $test1 = $normalizer->denormalize($data + ['type' => 'first'], AbstractNestedDummyWithConstructorAndDiscriminator::class, 'any');
        $this->assertInstanceOf(FirstNestedDummyWithConstructorAndDiscriminator::class, $test1);
        $this->assertSame('foo', $test1->foo);
        $this->assertSame('notfoo', $test1->notfoo);
        $this->assertSame('baz', $test1->baz);

        $test2 = $normalizer->denormalize($data + ['type' => 'second'], AbstractNestedDummyWithConstructorAndDiscriminator::class, 'any');
        $this->assertInstanceOf(SecondNestedDummyWithConstructorAndDiscriminator::class, $test2);
        $this->assertSame('quux', $test2->quux);
        $this->assertSame('notfoo', $test2->notfoo);
        $this->assertSame('baz', $test2->baz);
    }

    public function testNormalizeWithNestedAttributesMixingArrayTypes()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The element you are trying to set is already populated: "[one][two]"');
        $foobar = new AlreadyPopulatedNestedDummy();
        $foobar->foo = 'foo';
        $foobar->bar = 'bar';
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
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
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
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
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
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

    public function testNormalizeWithNestedAttributesInConstructor()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));

        $test = $normalizer->normalize(new NestedDummyWithConstructor('foo', 'quux', 'notfoo', 'baz'), 'any');
        $this->assertSame([
            'one' => [
                'two' => [
                    'three' => 'foo',
                ],
                'four' => 'quux',
            ],
            'foo' => 'notfoo',
            'baz' => 'baz',
        ], $test);
    }

    public function testNormalizeWithNestedAttributesInConstructorAndDiscriminatorMap()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));

        $test1 = $normalizer->normalize(new FirstNestedDummyWithConstructorAndDiscriminator('foo', 'notfoo', 'baz'), 'any');
        $this->assertSame([
            'type' => 'first',
            'one' => [
                'two' => [
                    'three' => 'foo',
                ],
            ],
            'foo' => 'notfoo',
            'baz' => 'baz',
        ], $test1);

        $test2 = $normalizer->normalize(new SecondNestedDummyWithConstructorAndDiscriminator('quux', 'notfoo', 'baz'), 'any');
        $this->assertSame([
            'type' => 'second',
            'one' => [
                'four' => 'quux',
            ],
            'foo' => 'notfoo',
            'baz' => 'baz',
        ], $test2);
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
        $extractor = $this->createStub(PhpDocExtractor::class);
        $extractor->method('getTypes')
            ->willReturn(
                [new Type('array', false, null, true, new Type('int'), new Type('object', false, DummyChild::class))],
                null
            );

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
        $extractor = $this->createStub(PhpDocExtractor::class);
        $extractor->method('getTypes')
            ->willReturn(
                [new Type('array', false, null, true, new Type('int'), new Type('string'))],
                null
            );

        $denormalizer = new AbstractObjectNormalizerCollectionDummy(null, null, $extractor);
        $arrayDenormalizer = new ArrayDenormalizerDummy();
        $serializer = new SerializerCollectionDummy([$arrayDenormalizer, $denormalizer]);
        $arrayDenormalizer->setSerializer($serializer);
        $denormalizer->setSerializer($serializer);

        return $denormalizer;
    }

    public function testDenormalizeWithDiscriminatorMapUsesCorrectClassname()
    {
        $factory = new ClassMetadataFactory(new AttributeLoader());

        $loaderMock = new class implements ClassMetadataFactoryInterface {
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

    public function testDenormalizeWithDiscriminatorMapAndObjectToPopulateUsesCorrectClassname()
    {
        $factory = new ClassMetadataFactory(new AttributeLoader());

        $loaderMock = new class implements ClassMetadataFactoryInterface {
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

        $data = [
            'foo' => 'foo',
            'quux' => ['value' => 'quux'],
        ];

        $normalizedData1 = $normalizer->denormalize($data + ['bar' => 'bar'], AbstractDummy::class, 'any', [
            AbstractNormalizer::OBJECT_TO_POPULATE => new AbstractDummyFirstChild('notfoo', 'notbar'),
        ]);
        $this->assertInstanceOf(AbstractDummyFirstChild::class, $normalizedData1);
        $this->assertSame('foo', $normalizedData1->foo);
        $this->assertSame('notbar', $normalizedData1->bar);
        $this->assertInstanceOf(DummyFirstChildQuux::class, $normalizedData1->quux);
        $this->assertSame('quux', $normalizedData1->quux->getValue());

        $normalizedData2 = $normalizer->denormalize($data + ['baz' => 'baz'], AbstractDummy::class, 'any', [
            AbstractNormalizer::OBJECT_TO_POPULATE => new AbstractDummySecondChild('notfoo', 'notbaz'),
        ]);
        $this->assertInstanceOf(AbstractDummySecondChild::class, $normalizedData2);
        $this->assertSame('foo', $normalizedData2->foo);
        $this->assertSame('baz', $normalizedData2->baz);
        $this->assertInstanceOf(DummySecondChildQuux::class, $normalizedData2->quux);
        $this->assertSame('quux', $normalizedData2->quux->getValue());
    }

    public function testDenormalizeWithNestedDiscriminatorMap()
    {
        $classDiscriminatorResolver = new class implements ClassDiscriminatorResolverInterface {
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

    /**
     * @dataProvider provideDiscriminatorAwareNormalizers
     */
    public function testDenormalizeDiscriminatorMapRespectsIgnoreOnTheMappedClass(AbstractObjectNormalizer $normalizer)
    {
        $data = ['type' => 'restricted', 'name' => 'foo', 'verified' => true, 'secret' => 'leaked'];

        $object = $normalizer->denormalize($data, RestrictedDiscriminatorBase::class);

        $this->assertInstanceOf(RestrictedDiscriminatorChild::class, $object);
        $this->assertSame('foo', $object->getName());
        $this->assertFalse($object->getVerified());
        $this->assertSame('default', $object->getSecret());

        try {
            $normalizer->denormalize($data, RestrictedDiscriminatorBase::class, null, [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false]);
            $this->fail(ExtraAttributesException::class.' expected');
        } catch (ExtraAttributesException $e) {
            $this->assertEqualsCanonicalizing(['verified', 'secret'], $e->getExtraAttributes());
        }
    }

    /**
     * @dataProvider provideDiscriminatorAwareNormalizers
     */
    public function testDenormalizeDiscriminatorMapRespectsGroupsOfTheMappedClass(AbstractObjectNormalizer $normalizer)
    {
        $context = [AbstractNormalizer::GROUPS => ['write']];

        $restricted = $normalizer->denormalize(['type' => 'restricted', 'name' => 'foo', 'role' => 'admin'], RestrictedDiscriminatorBase::class, null, $context);

        $this->assertInstanceOf(RestrictedDiscriminatorChild::class, $restricted);
        $this->assertSame('foo', $restricted->getName());
        $this->assertSame('user', $restricted->getRole());

        $open = $normalizer->denormalize(['type' => 'open', 'name' => 'foo', 'role' => 'admin'], RestrictedDiscriminatorBase::class, null, $context);

        $this->assertInstanceOf(OpenDiscriminatorChild::class, $open);
        $this->assertSame('admin', $open->getRole());
    }

    /**
     * @dataProvider provideDiscriminatorAwareNormalizers
     */
    public function testDenormalizeObjectToPopulateRespectsIgnoreOnItsClass(AbstractObjectNormalizer $normalizer)
    {
        $object = $normalizer->denormalize(['name' => 'foo', 'verified' => true], RestrictedDiscriminatorBase::class, null, [
            AbstractNormalizer::OBJECT_TO_POPULATE => $child = new RestrictedDiscriminatorChild(),
        ]);

        $this->assertSame($child, $object);
        $this->assertSame('foo', $object->getName());
        $this->assertFalse($object->getVerified());

        $object = $normalizer->denormalize(['verified' => true], UnmappedParent::class, null, [
            AbstractNormalizer::OBJECT_TO_POPULATE => $child = new UnmappedChildWithIgnoredAttribute(),
        ]);

        $this->assertSame($child, $object);
        $this->assertFalse($object->getVerified());
    }

    /**
     * @dataProvider provideDiscriminatorAwareNormalizers
     */
    public function testDenormalizeNestedDiscriminatorMapRespectsIgnoreOnTheMappedClass(AbstractObjectNormalizer $normalizer)
    {
        $data = ['type' => 'middle', 'nested_type' => 'leaf', 'name' => 'foo', 'verified' => true];

        $object = $normalizer->denormalize($data, NestedRestrictedDiscriminatorBase::class);

        $this->assertInstanceOf(NestedRestrictedDiscriminatorLeaf::class, $object);
        $this->assertSame('foo', $object->getName());
        $this->assertFalse($object->getVerified());

        $object = $normalizer->denormalize(['type' => 'middle', 'nested_type' => 'leaf', 'name' => 'foo'], NestedRestrictedDiscriminatorBase::class, null, [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false]);

        $this->assertInstanceOf(NestedRestrictedDiscriminatorLeaf::class, $object);
        $this->assertSame('foo', $object->getName());
    }

    /**
     * @dataProvider provideDiscriminatorAwareNormalizers
     */
    public function testDenormalizeConcreteDiscriminatorMapRootIgnoresAttributesOfOtherMappedClasses(AbstractObjectNormalizer $normalizer)
    {
        $root = $normalizer->denormalize(['type' => 'root', 'role' => 'admin'], ConcreteRestrictedDiscriminatorRoot::class);

        $this->assertInstanceOf(ConcreteRestrictedDiscriminatorRoot::class, $root);
        $this->assertSame('user', $root->getRole());

        $other = $normalizer->denormalize(['type' => 'other', 'role' => 'admin'], ConcreteRestrictedDiscriminatorRoot::class);

        $this->assertInstanceOf(ConcreteRestrictedDiscriminatorOther::class, $other);
        $this->assertSame('admin', $other->getRole());
    }

    /**
     * @dataProvider provideDiscriminatorAwareNormalizers
     */
    public function testDenormalizeDeepObjectToPopulateRespectsIgnoreOnItsClass(AbstractObjectNormalizer $normalizer)
    {
        $normalizer->setSerializer(new Serializer([$normalizer]));
        $holder = new RestrictedDiscriminatorHolder();
        $holder->setNotification($child = new RestrictedDiscriminatorChild());

        $normalizer->denormalize(['notification' => ['name' => 'foo', 'verified' => true]], RestrictedDiscriminatorHolder::class, null, [
            AbstractNormalizer::OBJECT_TO_POPULATE => $holder,
            AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE => true,
        ]);

        $this->assertSame($child, $holder->getNotification());
        $this->assertSame('foo', $child->getName());
        $this->assertFalse($child->getVerified());
    }

    /**
     * @dataProvider provideDiscriminatorAwareNormalizers
     */
    public function testDenormalizeObjectToPopulateIgnoresThePropertiesOfDoctrineProxies(AbstractObjectNormalizer $normalizer)
    {
        if (!interface_exists(Proxy::class)) {
            $this->markTestSkipped('The "doctrine/persistence" package is not installed.');
        }

        $proxy = new class extends RestrictedDiscriminatorChild implements Proxy {
            public $__initializer__;
            public $__cloner__;
            public $__isInitialized__ = false;

            public function __load(): void
            {
            }

            public function __isInitialized(): bool
            {
                return $this->__isInitialized__;
            }
        };

        $object = $normalizer->denormalize(['name' => 'foo', '__initializer__' => 'bar', '__cloner__' => 'bar', '__isInitialized__' => true], RestrictedDiscriminatorBase::class, null, [
            AbstractNormalizer::OBJECT_TO_POPULATE => $proxy,
        ]);

        $this->assertSame($proxy, $object);
        $this->assertSame('foo', $proxy->getName());
        $this->assertNull($proxy->__initializer__);
        $this->assertNull($proxy->__cloner__);
        $this->assertFalse($proxy->__isInitialized__);
    }

    /**
     * @dataProvider provideNormalizersWithObjectClassResolver
     */
    public function testDenormalizeObjectToPopulateComputesTheAllowedAttributesForTheResolvedClass(AbstractObjectNormalizer $normalizer)
    {
        $object = $normalizer->denormalize(['name' => 'foo', 'role' => 'admin'], RestrictedDiscriminatorBase::class, null, [
            AbstractNormalizer::GROUPS => ['write'],
            AbstractNormalizer::OBJECT_TO_POPULATE => $child = new RestrictedDiscriminatorChildWithWritableRole(),
        ]);

        $this->assertSame($child, $object);
        $this->assertSame('foo', $child->getName());
        $this->assertSame('user', $child->getRole());
    }

    /**
     * @dataProvider provideNormalizersWithObjectClassResolver
     */
    public function testDenormalizeObjectToPopulateIgnoresAResolvedClassItIsNotAnInstanceOf(AbstractObjectNormalizer $normalizer)
    {
        $object = $normalizer->denormalize(['name' => 'foo'], RestrictedDiscriminatorBase::class, null, [
            AbstractNormalizer::OBJECT_TO_POPULATE => $child = new OpenDiscriminatorChild(),
        ]);

        $this->assertSame($child, $object);
    }

    /**
     * @dataProvider provideDiscriminatorAwareNormalizers
     */
    public function testDenormalizeNestedDiscriminatorMapPassesTheTypePropertiesToTheConstructor(AbstractObjectNormalizer $normalizer)
    {
        $data = ['type' => 'middle', 'nested_type' => 'leaf', 'name' => 'foo'];

        $object = $normalizer->denormalize($data, NestedConstructorDiscriminatorBase::class, null, [AbstractNormalizer::GROUPS => ['write']]);

        $this->assertInstanceOf(NestedConstructorDiscriminatorLeaf::class, $object);
        $this->assertSame('middle', $object->getType());
        $this->assertSame('foo', $object->getName());

        $object = $normalizer->denormalize($data, NestedConstructorDiscriminatorBase::class, null, [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false]);

        $this->assertSame('middle', $object->getType());
    }

    /**
     * @dataProvider provideDiscriminatorAwareNormalizers
     */
    public function testDenormalizeIntoAMappedClassIgnoresTheTypePropertiesOfOuterMaps(AbstractObjectNormalizer $normalizer)
    {
        $object = $normalizer->denormalize(['type' => 'client', 'name' => 'foo'], NestedRestrictedDiscriminatorLeafWithIgnoredType::class);

        $this->assertSame('server', $object->getType());
        $this->assertSame('foo', $object->getName());
    }

    /**
     * @dataProvider provideDiscriminatorAwareNormalizers
     */
    public function testDenormalizeDiscriminatorMapDoesNotWriteAnIgnoredTypeProperty(AbstractObjectNormalizer $normalizer)
    {
        $object = $normalizer->denormalize(['type' => 'middle', 'nested_type' => 'leaf_with_ignored_type', 'name' => 'foo'], NestedRestrictedDiscriminatorBase::class);

        $this->assertInstanceOf(NestedRestrictedDiscriminatorLeafWithIgnoredType::class, $object);
        $this->assertSame('server', $object->getType());
        $this->assertSame('foo', $object->getName());

        $object = $normalizer->denormalize(['type' => 'client', 'name' => 'foo'], NestedRestrictedDiscriminatorBase::class, null, [
            AbstractNormalizer::OBJECT_TO_POPULATE => $leaf = new NestedRestrictedDiscriminatorLeafWithIgnoredType(),
        ]);

        $this->assertSame($leaf, $object);
        $this->assertSame('server', $object->getType());
        $this->assertSame('foo', $object->getName());
    }

    /**
     * @dataProvider provideDiscriminatorAwareNormalizers
     */
    public function testDenormalizeDiscriminatorMapDoesNotWriteATypePropertyOfOtherGroups(AbstractObjectNormalizer $normalizer)
    {
        $data = ['type' => 'admin_type', 'name' => 'foo'];
        $context = [AbstractNormalizer::GROUPS => ['write'], AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false];

        $object = $normalizer->denormalize($data, RestrictedDiscriminatorBase::class, null, $context);

        $this->assertInstanceOf(AdminTypeDiscriminatorChild::class, $object);
        $this->assertSame('server', $object->getType());
        $this->assertSame('foo', $object->getName());

        $object = $normalizer->denormalize($data, RestrictedDiscriminatorBase::class, null, [AbstractNormalizer::GROUPS => ['write', 'admin']] + $context);

        $this->assertSame('admin_type', $object->getType());
    }

    public static function provideDiscriminatorAwareNormalizers(): iterable
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $discriminator = new ClassDiscriminatorFromClassMetadata($classMetadataFactory);
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);

        yield 'ObjectNormalizer' => [new ObjectNormalizer($classMetadataFactory, null, null, $extractor, $discriminator)];
        yield 'PropertyNormalizer' => [new PropertyNormalizer($classMetadataFactory, null, $extractor, $discriminator)];
        yield 'GetSetMethodNormalizer' => [new GetSetMethodNormalizer($classMetadataFactory, null, $extractor, $discriminator)];
    }

    public static function provideNormalizersWithObjectClassResolver(): iterable
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $discriminator = new ClassDiscriminatorFromClassMetadata($classMetadataFactory);
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $objectClassResolver = static fn (object $object): string => match (true) {
            $object instanceof RestrictedDiscriminatorChildWithWritableRole => RestrictedDiscriminatorChild::class,
            $object instanceof OpenDiscriminatorChild => \stdClass::class,
            default => $object::class,
        };

        yield 'ObjectNormalizer' => [new ObjectNormalizer($classMetadataFactory, null, null, $extractor, $discriminator, $objectClassResolver)];
        yield 'PropertyNormalizer' => [new PropertyNormalizer($classMetadataFactory, null, $extractor, $discriminator, $objectClassResolver)];
        yield 'GetSetMethodNormalizer' => [new GetSetMethodNormalizer($classMetadataFactory, null, $extractor, $discriminator, $objectClassResolver)];
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
        $extractor = $this->createStub(PhpDocExtractor::class);
        $extractor->method('getTypes')
            ->willReturn(
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
            );

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
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory), null, $extractor);
        $serializer = new Serializer([new DateTimeNormalizer([DateTimeNormalizer::FORMAT_KEY => 'd-m-Y']), $normalizer]);

        /** @var ObjectDummyWithContextAttribute $obj */
        $obj = $serializer->denormalize(['property_with_serialized_name' => '01-02-2022', 'propertyWithoutSerializedName' => '01-02-2022'], ObjectDummyWithContextAttribute::class);

        $this->assertSame($obj->propertyWithSerializedName->format('Y-m-d'), $obj->propertyWithoutSerializedName->format('Y-m-d'));
    }

    public function testNormalizeUsesContextAttributeForPropertiesInConstructorWithSerializedPath()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory), null, $extractor);
        $serializer = new Serializer([new DateTimeNormalizer(), $normalizer]);

        $obj = new ObjectDummyWithContextAttributeAndSerializedPath(new \DateTimeImmutable('22-02-2023'));

        $data = $serializer->normalize($obj);

        $this->assertSame(['property' => ['with_path' => '02-22-2023']], $data);
    }

    public function testNormalizeUsesContextAttributeForProperties()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory), null, $extractor);
        $serializer = new Serializer([$normalizer]);

        $obj = new ObjectDummyWithContextAttributeSkipNullValues();

        $data = $serializer->normalize($obj);

        $this->assertSame(['propertyWithoutNullSkipNullValues' => 'foo'], $data);
    }

    public function testDefaultExcludeFromCacheKey()
    {
        $object = new DummyChild();
        $object->bar = 'not called';

        $normalizer = new class(null, null, null, null, null, [AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY => ['foo']]) extends AbstractObjectNormalizerDummy {
            public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
            {
                AbstractObjectNormalizerTest::assertContains('foo', $this->defaultContext[ObjectNormalizer::EXCLUDE_FROM_CACHE_KEY]);
                $data->bar = 'called';

                return true;
            }
        };

        $serializer = new Serializer([$normalizer]);
        $serializer->normalize($object);

        $this->assertSame('called', $object->bar);
    }

    public function testDenormalizeUnionOfEnums()
    {
        $serializer = new Serializer([
            new BackedEnumNormalizer(),
            new ObjectNormalizer(
                classMetadataFactory: new ClassMetadataFactory(new AttributeLoader()),
                propertyTypeExtractor: new PropertyInfoExtractor([], [new ReflectionExtractor()]),
            ),
        ]);

        $normalized = $serializer->normalize(new DummyWithEnumUnion(EnumA::A));
        $this->assertEquals(new DummyWithEnumUnion(EnumA::A), $serializer->denormalize($normalized, DummyWithEnumUnion::class));

        $normalized = $serializer->normalize(new DummyWithEnumUnion(EnumB::B));
        $this->assertEquals(new DummyWithEnumUnion(EnumB::B), $serializer->denormalize($normalized, DummyWithEnumUnion::class));
    }

    public function testDenormalizeWithNumberAsSerializedNameAndNoArrayReindex()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadata();

        $data = [
            '1' => 'foo',
            '99' => 'baz',
        ];

        $obj = new class {
            #[SerializedName('1')]
            public $foo;

            #[SerializedName('99')]
            public $baz;
        };

        $test = $normalizer->denormalize($data, $obj::class);
        $this->assertSame('foo', $test->foo);
        $this->assertSame('baz', $test->baz);
    }

    public function testDenormalizeWithCorrectOrderOfAttributeAndProperty()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadata();

        $data = [
            'id' => 'root-level-id',
            'data' => [
                'id' => 'nested-id',
            ],
        ];

        $obj = new class {
            #[SerializedPath('[data][id]')]
            public $id;
        };

        $test = $normalizer->denormalize($data, $obj::class);
        $this->assertSame('nested-id', $test->id);
    }

    public function testDenormalizeMissingAndNullNestedValues()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadata();

        $data = [
            'data' => [
                'foo' => null,
            ],
        ];

        $obj = new class {
            #[SerializedPath('[data][foo]')]
            public ?string $foo;

            #[SerializedPath('[data][bar]')]
            public ?string $bar;
        };

        $test = $normalizer->denormalize($data, $obj::class);
        $this->assertNull($test->foo);
        $this->assertFalse((new \ReflectionProperty($obj, 'bar'))->isInitialized($obj));
    }

    public function testDenormalizeNullCoalescingValues()
    {
        if (!method_exists(PropertyPath::class, 'isNullSafe')) {
            $this->markTestSkipped('null coalescing property path is not supported before symfony/property-access 6.2');
        }

        $normalizer = new AbstractObjectNormalizerWithMetadata();

        $data = [
            'data' => [
                'foo' => 'test',
            ],
            'empty_data' => null,
        ];

        $obj = new class {
            #[SerializedPath('[data][foo?]')]
            public ?string $foo;

            #[SerializedPath('[data][bar?]')]
            public ?string $bar;

            #[SerializedPath('[empty_data?][nothing]')]
            public ?string $nothing;

            #[SerializedPath('[not_set?][nothing]')]
            public ?string $notSet;
        };

        $test = $normalizer->denormalize($data, $obj::class);
        $this->assertSame('test', $test->foo);
        $this->assertFalse((new \ReflectionProperty($obj, 'bar'))->isInitialized($obj));
        $this->assertNull($test->nothing);
        $this->assertFalse((new \ReflectionProperty($obj, 'notSet'))->isInitialized($obj));
    }

    public function testNormalizeBasedOnAllowedAttributes()
    {
        $normalizer = new class extends AbstractObjectNormalizer {
            protected function getAllowedAttributes($classOrObject, array $context, bool $attributesAsString = false): array
            {
                return ['foo'];
            }

            protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
            {
                return [];
            }

            protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
            {
                return $object->$attribute;
            }

            protected function setAttributeValue(object $object, string $attribute, $value, ?string $format = null, array $context = []): void
            {
            }
        };

        $object = new Dummy();
        $object->foo = 'foo';
        $object->bar = 'bar';

        $this->assertSame(['foo' => 'foo'], $normalizer->normalize($object));
    }

    public function testDenormalizeUntypedFormat()
    {
        $serializer = new Serializer([new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]))]);
        $actual = $serializer->denormalize(['value' => ''], DummyWithObjectOrNull::class, 'xml');

        $this->assertEquals(new DummyWithObjectOrNull(null), $actual);
    }

    public function testDenormalizeUntypedFormatNotNormalizable()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Custom exception message');
        $serializer = new Serializer([new CustomNormalizer(), new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]))]);
        $serializer->denormalize(['value' => 'test'], DummyWithNotNormalizable::class, 'xml');
    }

    public function testDenormalizeUntypedFormatMissingArg()
    {
        $this->expectException(MissingConstructorArgumentsException::class);
        $serializer = new Serializer([new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]))]);
        $serializer->denormalize(['value' => 'invalid'], DummyWithObjectOrNull::class, 'xml');
    }

    public function testDenormalizeUntypedFormatScalar()
    {
        $serializer = new Serializer([new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]))]);
        $actual = $serializer->denormalize(['value' => 'false'], DummyWithObjectOrBool::class, 'xml');

        $this->assertEquals(new DummyWithObjectOrBool(false), $actual);
    }

    public function testDenormalizeUntypedStringObject()
    {
        $serializer = new Serializer([new CustomNormalizer(), new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]))]);
        $actual = $serializer->denormalize(['value' => ''], DummyWithStringObject::class, 'xml');

        $this->assertEquals(new DummyWithStringObject(new DummyString()), $actual);
        $this->assertEquals('', $actual->value->value);
    }

    public function testDenormalizeStringKeyedCollectionDecodedFromXml()
    {
        $serializer = new Serializer([new ArrayDenormalizer(), new BackedEnumNormalizer(), new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]))]);
        $actual = $serializer->denormalize(['children' => ['a' => ['bar' => 'first']], 'enums' => ['a' => 'a'], 'strings' => ['a' => 'first']], StringKeyedCollectionsDummy::class, 'xml');

        $child = new DummyChild();
        $child->bar = 'first';
        $this->assertEquals(['a' => $child], $actual->children);
        $this->assertSame(['a' => EnumA::A], $actual->enums);
        $this->assertSame(['a' => 'first'], $actual->strings);
    }

    public function testDenormalizeUnionOfCollectionAndScalarDecodedFromXml()
    {
        $serializer = new Serializer([new ArrayDenormalizer(), new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]))]);
        $actual = $serializer->denormalize(['children' => 'plain'], DummyWithChildrenOrString::class, 'xml');

        $this->assertSame('plain', $actual->children);
    }

    public function testDenormalizeObjectIntoListOfObjects()
    {
        $serializer = new Serializer([new ArrayDenormalizer(), new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]))]);

        try {
            $serializer->denormalize(['children' => new \DateTimeImmutable()], DummyWithListOfChildren::class);
            $this->fail('A NotNormalizableValueException should have been thrown.');
        } catch (NotNormalizableValueException $e) {
            $this->assertSame(\sprintf('The type of the "children" attribute for class "%s" must be one of "%s[]" ("DateTimeImmutable" given).', DummyWithListOfChildren::class, DummyChildWithConstructor::class), $e->getMessage());
        }

        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage(\sprintf('The type of the "children" attribute for class "%s" must be one of "%s[]" ("stdClass" given).', DummyWithListOfChildren::class, DummyChildWithConstructor::class));

        $serializer->denormalize(['children' => (object) ['bar' => 'first']], DummyWithListOfChildren::class);
    }

    public function testDenormalizeObjectIntoObjectCollectionOfObjects()
    {
        $serializer = new Serializer([new ArrayDenormalizer(), new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]))]);
        $children = new \ArrayObject([new DummyChildWithConstructor('first')]);

        $this->assertSame($children, $serializer->denormalize(['children' => $children], DummyWithArrayObjectOfChildren::class)->children);
    }

    public function testDenormalizeArrayIntoListOfObjects()
    {
        $serializer = new Serializer([new ArrayDenormalizer(), new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]))]);
        $actual = $serializer->denormalize(['children' => [['bar' => 'first']]], DummyWithListOfChildren::class);

        $this->assertEquals([new DummyChildWithConstructor('first')], $actual->children);
    }

    /**
     * @dataProvider provideScalarsForUnionWithObjectAndScalarTypes
     */
    public function testDenormalizeScalarIntoUnionWithObjectTypeTriesTheOtherTypes(string $attribute, mixed $data, mixed $expected)
    {
        $actual = $this->getDenormalizerForUnionsWithObjectType()->denormalize([$attribute => $data], DummyWithObjectOrScalarUnion::class);

        $this->assertSame($expected, $actual->$attribute);
    }

    public static function provideScalarsForUnionWithObjectAndScalarTypes(): iterable
    {
        yield 'int' => ['int', 5, 5];
        yield 'int from phpdoc' => ['docBlock', 5, 5];
        yield 'string' => ['string', 'abc', 'abc'];
        yield 'backed enum' => ['enum', 'a', EnumA::A];
    }

    /**
     * @dataProvider provideScalarsForObjectTypesDenormalizedFromScalars
     */
    public function testDenormalizeScalarIntoUnionKeepsObjectTypesDenormalizedFromScalars(string $attribute, mixed $data, object $expected, ?string $format = null)
    {
        $actual = $this->getDenormalizerForUnionsWithObjectType()->denormalize([$attribute => $data], DummyWithObjectOrScalarUnion::class, $format);

        $this->assertEquals($expected, $actual->$attribute);
    }

    public static function provideScalarsForObjectTypesDenormalizedFromScalars(): iterable
    {
        yield 'date' => ['date', '2024-01-01T00:00:00+00:00', new \DateTimeImmutable('2024-01-01T00:00:00+00:00')];
        yield 'backed enum' => ['enumOrString', 200, IntegerBackedEnumDummy::SUCCESS];

        $string = new DummyString();
        $string->value = 5;
        yield 'denormalizable' => ['denormalizable', 5, $string];

        $xml = new XmlScalarDummy();
        $xml->value = '5';
        yield 'xml' => ['xml', '5', $xml, 'xml'];
    }

    /**
     * @dataProvider provideScalarsMatchingNoTypeOfUnionWithObjectType
     */
    public function testDenormalizeScalarMatchingNoTypeOfUnionWithObjectType(string $attribute, mixed $data, string $expectedMessage)
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->getDenormalizerForUnionsWithObjectType()->denormalize([$attribute => $data], DummyWithObjectOrScalarUnion::class);
    }

    public static function provideScalarsMatchingNoTypeOfUnionWithObjectType(): iterable
    {
        yield 'string' => ['int', 'abc', \sprintf('The type of the "int" attribute for class "%s" must be one of "%s", "int" ("string" given).', DummyWithObjectOrScalarUnion::class, DummyChild::class)];
        yield 'null' => ['int', null, \sprintf('The type of the "int" attribute for class "%s" must be one of "%s", "int" ("null" given).', DummyWithObjectOrScalarUnion::class, DummyChild::class)];
        yield 'nullable' => ['nullable', 5, \sprintf('The type of the "nullable" attribute for class "%s" must be one of "%s" ("int" given).', DummyWithObjectOrScalarUnion::class, DummyChild::class)];
    }

    /**
     * @dataProvider provideScalarsMatchingNoTypeOfUntypedUnionWithObjectType
     */
    public function testDenormalizeScalarMatchingNoTypeOfUnionWithObjectTypeWithoutTypeEnforcement(string $attribute, mixed $data)
    {
        $actual = $this->getDenormalizerForUnionsWithObjectType()->denormalize([$attribute => $data], DummyWithObjectOrScalarUnion::class, null, [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true]);

        $this->assertSame($data, $actual->$attribute);
    }

    public static function provideScalarsMatchingNoTypeOfUntypedUnionWithObjectType(): iterable
    {
        yield 'string' => ['docBlock', 'abc'];
        yield 'nullable' => ['nullableDocBlock', 5];
    }

    public function testDebugTraceIdDoesNotChangeContextCacheKey()
    {
        $normalizer = new class extends AbstractObjectNormalizerDummy {
            public int $extractAttributesCalls = 0;

            protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
            {
                ++$this->extractAttributesCalls;

                return array_keys((array) $object);
            }

            protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
            {
                return $object->{$attribute};
            }
        };

        $serializer = new Serializer([$normalizer]);

        $dummy = new Dummy();
        $dummy->foo = 'foo';

        $serializer->normalize($dummy, null, [TraceableSerializer::DEBUG_TRACE_ID => 'first']);
        $serializer->normalize($dummy, null, [TraceableSerializer::DEBUG_TRACE_ID => 'second']);

        $this->assertSame(1, $normalizer->extractAttributesCalls);
    }

    public function testProvidingContextCacheKeyGeneratesSameChildContextCacheKey()
    {
        $foobar = new Dummy();
        $foobar->foo = new EmptyDummy();
        $foobar->bar = 'bar';
        $foobar->baz = 'baz';

        $normalizer = new class extends AbstractObjectNormalizerDummy {
            public $childContextCacheKey;

            protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
            {
                return array_keys((array) $object);
            }

            protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
            {
                return $object->{$attribute};
            }

            protected function createChildContext(array $parentContext, string $attribute, ?string $format): array
            {
                $childContext = parent::createChildContext($parentContext, $attribute, $format);
                $this->childContextCacheKey = $childContext['cache_key'];

                return $childContext;
            }
        };

        $serializer = new Serializer([$normalizer]);

        $serializer->normalize($foobar, null, ['cache_key' => 'hardcoded', 'iri' => '/dummy/1']);
        $firstChildContextCacheKey = $normalizer->childContextCacheKey;

        $serializer->normalize($foobar, null, ['cache_key' => 'hardcoded', 'iri' => '/dummy/2']);
        $secondChildContextCacheKey = $normalizer->childContextCacheKey;

        $this->assertSame($firstChildContextCacheKey, $secondChildContextCacheKey);
    }

    public function testChildContextChangesContextCacheKey()
    {
        $foobar = new Dummy();
        $foobar->foo = new EmptyDummy();
        $foobar->bar = 'bar';
        $foobar->baz = 'baz';

        $normalizer = new class extends AbstractObjectNormalizerDummy {
            public array $childContextCacheKeys = [];

            protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
            {
                return array_keys((array) $object);
            }

            protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
            {
                return $object->{$attribute};
            }

            protected function createChildContext(array $parentContext, string $attribute, ?string $format): array
            {
                $childContext = parent::createChildContext($parentContext, $attribute, $format);
                $this->childContextCacheKeys[$attribute] = $childContext['cache_key'];

                return $childContext;
            }
        };

        $serializer = new Serializer([$normalizer]);
        $serializer->normalize($foobar, null, ['cache_key' => 'hardcoded', 'iri' => '/dummy/1']);

        $this->assertSame(['foo' => 'hardcoded-foo', 'bar' => 'hardcoded-bar', 'baz' => 'hardcoded-baz'], $normalizer->childContextCacheKeys);
    }

    public function testChildContextCacheKeyStaysFalseWhenOriginalCacheKeyIsFalse()
    {
        $foobar = new Dummy();
        $foobar->foo = new EmptyDummy();
        $foobar->bar = 'bar';
        $foobar->baz = 'baz';

        $normalizer = new class extends AbstractObjectNormalizerDummy {
            public $childContextCacheKey;

            protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
            {
                return array_keys((array) $object);
            }

            protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
            {
                return $object->{$attribute};
            }

            protected function createChildContext(array $parentContext, string $attribute, ?string $format): array
            {
                $childContext = parent::createChildContext($parentContext, $attribute, $format);
                $this->childContextCacheKey = $childContext['cache_key'];

                return $childContext;
            }
        };

        $serializer = new Serializer([$normalizer]);
        $serializer->normalize($foobar, null, ['cache_key' => false]);

        $this->assertFalse($normalizer->childContextCacheKey);
    }

    public function testCollectionItemsShareTheSameCacheKey()
    {
        $nameConverter = new class implements NameConverterInterface {
            public $cacheKeys = [];

            public function normalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
            {
                return $propertyName;
            }

            public function denormalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
            {
                $this->cacheKeys[$context['cache_key']] = true;

                return $propertyName;
            }
        };

        $serializer = new Serializer([new ArrayDenormalizer(), new ObjectNormalizer(null, $nameConverter)]);
        $serializer->denormalize(['first' => ['foo' => 'a'], 'second' => ['foo' => 'b'], 'third' => ['foo' => 'c']], Dummy::class.'[]');

        $this->assertCount(1, $nameConverter->cacheKeys);
    }

    public function testNestedObjectsShareTheCachesOfTheNameConverter()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $serializer = new Serializer([new ObjectNormalizer($classMetadataFactory, $nameConverter, null, new PhpDocExtractor())]);

        $data = ['baz' => 'foo0'];
        for ($i = 1; $i < 100; ++$i) {
            $data = ['baz' => 'foo'.$i, 'child' => $data];
        }

        $object = $serializer->denormalize($data, SerializedNameDummy::class);

        for ($i = 99; $i >= 0; --$i) {
            $this->assertSame('foo'.$i, $object->foo);
            $object = $object->child;
        }

        $caches = \Closure::bind(fn () => [$this->denormalizeCache, $this->attributesMetadataCache], $nameConverter, MetadataAwareNameConverter::class)();

        foreach ($caches as $cache) {
            $this->assertCount(1, $cache);
        }
    }

    public function testAttributesCacheSizeLimit()
    {
        $createTree = function (int $depth) use (&$createTree) {
            $node = new Dummy();

            if ($depth) {
                $node->foo = $createTree($depth - 1);
                $node->bar = $createTree($depth - 1);
            }

            return $node;
        };
        $normalizer = new ObjectNormalizer();
        $serializer = new Serializer([$normalizer]);

        $data = $serializer->normalize($createTree(10));

        $this->assertNull($data['bar']['foo']['bar']['foo']['bar']['foo']['bar']['foo']['bar']['foo']['bar']);

        $attributesCache = \Closure::bind(fn () => $this->attributesCache, $normalizer, AbstractObjectNormalizer::class)();

        $this->assertLessThanOrEqual(1001, \count($attributesCache));
    }

    public function testTypesCacheSizeLimit()
    {
        $object = new class {
            public function __set(string $name, $value): void
            {
            }
        };
        $normalizer = new ObjectNormalizer(null, null, null, new ReflectionExtractor());

        for ($i = 0; $i < 2000; ++$i) {
            $normalizer->denormalize(['attribute'.$i => 'value'], $object::class, null, ['object_to_populate' => $object]);
        }

        $typesCache = \Closure::bind(fn () => $this->typesCache, $normalizer, AbstractObjectNormalizer::class)();

        $this->assertLessThanOrEqual(1001, \count($typesCache));
    }

    public function testDenormalizeXmlScalar()
    {
        $normalizer = new class extends AbstractObjectNormalizer {
            public function __construct()
            {
                parent::__construct(null, new MetadataAwareNameConverter(new ClassMetadataFactory(new AttributeLoader())));
            }

            protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
            {
                return [];
            }

            protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
            {
                return null;
            }

            protected function setAttributeValue(object $object, string $attribute, $value, ?string $format = null, array $context = []): void
            {
                $object->$attribute = $value;
            }
        };

        $this->assertSame('scalar', $normalizer->denormalize('scalar', XmlScalarDummy::class, 'xml')->value);
    }

    public function testNormalizationWithMaxDepthOnStdclassObjectDoesNotThrowWarning()
    {
        $object = new \stdClass();
        $object->string = 'yes';

        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory);
        $normalized = $normalizer->normalize($object, context: [
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
        ]);

        $this->assertSame(['string' => 'yes'], $normalized);
    }

    /**
     * @dataProvider provideBooleanTypesData
     */
    public function testDenormalizeBooleanTypesWithNotMatchingData(array $data, string $type)
    {
        $normalizer = new AbstractObjectNormalizerWithMetadataAndPropertyTypeExtractors();

        $this->expectException(NotNormalizableValueException::class);

        $normalizer->denormalize($data, $type);
    }

    public static function provideBooleanTypesData()
    {
        return [
            [['foo' => true], FalsePropertyDummy::class],
            [['foo' => false], TruePropertyDummy::class],
        ];
    }

    public function testDeserializeAndSerializeConstructorAndIgnoreAndInterfacedObjectsWithTheClassMetadataDiscriminator()
    {
        $example = new DummyMessageNumberFour('Hello');

        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $normalizer = new PropertyNormalizer(
            $classMetadataFactory,
            null,
            new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]),
            new ClassDiscriminatorFromClassMetadata($classMetadataFactory),
        );

        $serialized = $normalizer->normalize($example, 'json');
        $deserialized = $normalizer->denormalize($serialized, DummyMessageInterface::class, 'json');

        $this->assertEquals($example, $deserialized);
    }

    private function getDenormalizerForUnionsWithObjectType(): Serializer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);

        return new Serializer([new BackedEnumNormalizer(), new DateTimeNormalizer(), new CustomNormalizer(), new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory), null, $extractor)]);
    }
}

class AbstractObjectNormalizerDummy extends AbstractObjectNormalizer
{
    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
    {
        return [];
    }

    protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
    {
    }

    protected function setAttributeValue(object $object, string $attribute, $value, ?string $format = null, array $context = []): void
    {
        $object->$attribute = $value;
    }

    protected function isAllowedAttribute($classOrObject, string $attribute, ?string $format = null, array $context = []): bool
    {
        return \in_array($attribute, ['foo', 'baz', 'quux', 'value']);
    }

    public function instantiateObject(array &$data, string $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes, ?string $format = null): object
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
    #[SerializedPath('[one][two][three]')]
    public $foo;

    #[SerializedPath('[one][two]')]
    public $bar;
}

class DuplicateValueNestedDummy
{
    #[SerializedPath('[one][two][three]')]
    public $foo;

    #[SerializedPath('[one][two][three]')]
    public $bar;

    public $baz;
}

class NestedDummy
{
    #[SerializedPath('[one][two][three]')]
    public $foo;

    #[SerializedPath('[one][four]')]
    public $quux;

    #[SerializedPath('[foo]')]
    public $notfoo;

    public $baz;
}

class NestedDummyWithConstructor
{
    public function __construct(
        #[SerializedPath('[one][two][three]')]
        public $foo,

        #[SerializedPath('[one][four]')]
        public $quux,

        #[SerializedPath('[foo]')]
        public $notfoo,

        public $baz,
    ) {
    }
}

class SnakeCaseNestedDummy
{
    #[SerializedPath('[one][two_three]')]
    public $fooBar;
}

#[DiscriminatorMap(typeProperty: 'type', mapping: [
    'first' => FirstNestedDummyWithConstructorAndDiscriminator::class,
    'second' => SecondNestedDummyWithConstructorAndDiscriminator::class,
])]
abstract class AbstractNestedDummyWithConstructorAndDiscriminator
{
    public function __construct(
        #[SerializedPath('[foo]')]
        public $notfoo,

        public $baz,
    ) {
    }
}

class FirstNestedDummyWithConstructorAndDiscriminator extends AbstractNestedDummyWithConstructorAndDiscriminator
{
    public function __construct(
        #[SerializedPath('[one][two][three]')]
        public $foo,

        $notfoo,
        $baz,
    ) {
        parent::__construct($notfoo, $baz);
    }
}

class SecondNestedDummyWithConstructorAndDiscriminator extends AbstractNestedDummyWithConstructorAndDiscriminator
{
    public function __construct(
        #[SerializedPath('[one][four]')]
        public $quux,

        $notfoo,
        $baz,
    ) {
        parent::__construct($notfoo, $baz);
    }
}

class DuplicateKeyNestedDummy
{
    #[SerializedPath('[one][four]')]
    public $quux;

    #[SerializedName('quux')]
    public $notquux;
}

class SerializedNameDuplicateRawKeyDummy
{
    #[SerializedName('subproject_id')]
    public string $subproject;
}

class SerializedNameDuplicateRawKeyConstructorDummy
{
    public function __construct(
        #[SerializedName('subproject_id')]
        public string $subproject,
    ) {
    }
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
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        parent::__construct($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
    {
    }

    protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
    {
    }

    protected function setAttributeValue(object $object, string $attribute, $value, ?string $format = null, array $context = []): void
    {
        if (property_exists($object, $attribute)) {
            $object->$attribute = $value;
        }
    }
}

class DummyWithPlainObject
{
    /** @var object */
    public $plainObject;
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

class StringKeyedCollectionsDummy
{
    /** @var array<string, DummyChild> */
    public array $children = [];

    /** @var array<string, EnumA> */
    public array $enums = [];

    /** @var array<string, string> */
    public array $strings = [];
}

class DummyChildWithConstructor
{
    public function __construct(public string $bar)
    {
    }
}

class DummyWithChildrenOrString
{
    /** @var DummyChildWithConstructor[]|string */
    public array|string $children = [];
}

class DummyWithListOfChildren
{
    /**
     * @param list<DummyChildWithConstructor> $children
     */
    public function __construct(public array $children)
    {
    }
}

class DummyWithArrayObjectOfChildren
{
    /**
     * @param \ArrayObject<int, DummyChildWithConstructor> $children
     */
    public function __construct(public \ArrayObject $children)
    {
    }
}

class XmlScalarDummy
{
    #[SerializedName('#')]
    public $value;
}

class DummyWithObjectOrScalarUnion
{
    public DummyChild|int $int;
    /** @var DummyChild|int */
    public $docBlock;
    public DummyChild|string $string;
    public DummyChild|EnumA $enum;
    public ?DummyChild $nullable;
    /** @var DummyChild|null */
    public $nullableDocBlock;
    public \DateTimeImmutable|string $date;
    public IntegerBackedEnumDummy|string $enumOrString;
    public DummyString|int $denormalizable;
    public XmlScalarDummy|int $xml;
}

class FalsePropertyDummy
{
    /** @var false */
    public $foo;
}

class TruePropertyDummy
{
    /** @var true */
    public $foo;
}

class SerializerCollectionDummy implements SerializerInterface, DenormalizerInterface
{
    private array $normalizers;

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

    public function denormalize($data, string $type, ?string $format = null, array $context = []): mixed
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

    public function supportsDenormalization($data, string $type, ?string $format = null, array $context = []): bool
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

    protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
    {
    }

    protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
    {
    }

    protected function setAttributeValue(object $object, string $attribute, $value, ?string $format = null, array $context = []): void
    {
        $object->$attribute = $value;
    }

    protected function isAllowedAttribute($classOrObject, string $attribute, ?string $format = null, array $context = []): bool
    {
        return true;
    }

    public function instantiateObject(array &$data, string $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes, ?string $format = null): object
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
    private SerializerInterface&DenormalizerInterface $serializer;

    /**
     * @throws NotNormalizableValueException
     */
    public function denormalize($data, string $type, ?string $format = null, array $context = []): mixed
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

    public function supportsDenormalization($data, string $type, ?string $format = null, array $context = []): bool
    {
        return str_ends_with($type, '[]')
            && $this->serializer->supportsDenormalization($data, substr($type, 0, -2), $format, $context);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        \assert($serializer instanceof DenormalizerInterface);
        $this->serializer = $serializer;
    }
}

class NotSerializable
{
    public function __serialize(): array
    {
        throw new \Error('not serializable');
    }
}

enum EnumA: string
{
    case A = 'a';
}

enum EnumB: string
{
    case B = 'b';
}

class DummyWithEnumUnion
{
    public function __construct(
        public readonly EnumA|EnumB $enum,
    ) {
    }
}

class AbstractObjectNormalizerWithMetadataAndPropertyTypeExtractors extends AbstractObjectNormalizer
{
    public function __construct()
    {
        parent::__construct(new ClassMetadataFactory(new AttributeLoader()), null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
    {
        return [];
    }

    protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
    {
        return null;
    }

    protected function setAttributeValue(object $object, string $attribute, $value, ?string $format = null, array $context = []): void
    {
        if (property_exists($object, $attribute)) {
            $object->$attribute = $value;
        }
    }
}

#[DiscriminatorMap(typeProperty: 'type', mapping: [
    'restricted' => RestrictedDiscriminatorChild::class,
    'open' => OpenDiscriminatorChild::class,
    'admin_type' => AdminTypeDiscriminatorChild::class,
])]
abstract class RestrictedDiscriminatorBase
{
    #[Groups(['write'])]
    private string $name = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}

class RestrictedDiscriminatorChild extends RestrictedDiscriminatorBase
{
    #[Ignore]
    private bool $verified = false;

    private string $role = 'user';

    public function __construct(
        #[Ignore]
        private string $secret = 'default',
    ) {
    }

    public function getVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): void
    {
        $this->verified = $verified;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }
}

class RestrictedDiscriminatorChildWithWritableRole extends RestrictedDiscriminatorChild
{
    #[Groups(['write'])]
    public function setRole(string $role): void
    {
        parent::setRole($role);
    }
}

class OpenDiscriminatorChild extends RestrictedDiscriminatorBase
{
    #[Groups(['write'])]
    private string $role = 'user';

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }
}

class AdminTypeDiscriminatorChild extends RestrictedDiscriminatorBase
{
    #[Groups(['admin'])]
    private string $type = 'server';

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }
}

class UnmappedParent
{
}

class UnmappedChildWithIgnoredAttribute extends UnmappedParent
{
    #[Ignore]
    private bool $verified = false;

    public function getVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): void
    {
        $this->verified = $verified;
    }
}

#[DiscriminatorMap(typeProperty: 'type', mapping: [
    'middle' => NestedRestrictedDiscriminatorMiddle::class,
])]
abstract class NestedRestrictedDiscriminatorBase
{
    private string $name = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}

#[DiscriminatorMap(typeProperty: 'nested_type', mapping: [
    'leaf' => NestedRestrictedDiscriminatorLeaf::class,
    'leaf_with_ignored_type' => NestedRestrictedDiscriminatorLeafWithIgnoredType::class,
])]
abstract class NestedRestrictedDiscriminatorMiddle extends NestedRestrictedDiscriminatorBase
{
}

class NestedRestrictedDiscriminatorLeaf extends NestedRestrictedDiscriminatorMiddle
{
    #[Ignore]
    private bool $verified = false;

    public function getVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): void
    {
        $this->verified = $verified;
    }
}

class RestrictedDiscriminatorHolder
{
    private ?RestrictedDiscriminatorBase $notification = null;

    public function getNotification(): ?RestrictedDiscriminatorBase
    {
        return $this->notification;
    }

    public function setNotification(?RestrictedDiscriminatorBase $notification): void
    {
        $this->notification = $notification;
    }
}

class NestedRestrictedDiscriminatorLeafWithIgnoredType extends NestedRestrictedDiscriminatorMiddle
{
    #[Ignore]
    private string $type = 'server';

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }
}

#[DiscriminatorMap(typeProperty: 'type', mapping: [
    'middle' => NestedConstructorDiscriminatorMiddle::class,
])]
abstract class NestedConstructorDiscriminatorBase
{
}

#[DiscriminatorMap(typeProperty: 'nested_type', mapping: [
    'leaf' => NestedConstructorDiscriminatorLeaf::class,
])]
abstract class NestedConstructorDiscriminatorMiddle extends NestedConstructorDiscriminatorBase
{
}

class NestedConstructorDiscriminatorLeaf extends NestedConstructorDiscriminatorMiddle
{
    public function __construct(
        private string $type,
        #[Groups(['write'])]
        private string $name = '',
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

#[DiscriminatorMap(typeProperty: 'type', mapping: [
    'root' => ConcreteRestrictedDiscriminatorRoot::class,
    'other' => ConcreteRestrictedDiscriminatorOther::class,
])]
class ConcreteRestrictedDiscriminatorRoot
{
    #[Ignore]
    private string $role = 'user';

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }
}

class ConcreteRestrictedDiscriminatorOther
{
    private string $role = 'user';

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }
}
