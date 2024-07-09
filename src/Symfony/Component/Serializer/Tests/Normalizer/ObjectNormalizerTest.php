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

use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\Exception\InvalidTypeException;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\NameConverter\AdvancedNameConverterInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\GroupDummy;
use Symfony\Component\Serializer\Tests\Fixtures\CircularReferenceDummy;
use Symfony\Component\Serializer\Tests\Fixtures\DummyPrivatePropertyWithoutGetter;
use Symfony\Component\Serializer\Tests\Fixtures\OtherSerializedNameDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Php74Dummy;
use Symfony\Component\Serializer\Tests\Fixtures\Php74DummyPrivate;
use Symfony\Component\Serializer\Tests\Fixtures\Php80Dummy;
use Symfony\Component\Serializer\Tests\Fixtures\SiblingHolder;
use Symfony\Component\Serializer\Tests\Normalizer\Features\AttributesTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\CacheableObjectAttributesTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\CallbacksTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\CircularReferenceTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\ConstructorArgumentsTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\ContextMetadataTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\FilterBoolTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\GroupsTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\IgnoredAttributesTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\MaxDepthTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\ObjectDummy;
use Symfony\Component\Serializer\Tests\Normalizer\Features\ObjectToPopulateTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\SkipNullValuesTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\SkipUninitializedValuesTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\TypedPropertiesObject;
use Symfony\Component\Serializer\Tests\Normalizer\Features\TypedPropertiesObjectWithGetters;
use Symfony\Component\Serializer\Tests\Normalizer\Features\TypeEnforcementTestTrait;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ObjectNormalizerTest extends TestCase
{
    use AttributesTestTrait;
    use CacheableObjectAttributesTestTrait;
    use CallbacksTestTrait;
    use CircularReferenceTestTrait;
    use ConstructorArgumentsTestTrait;
    use ContextMetadataTestTrait;
    use FilterBoolTestTrait;
    use GroupsTestTrait;
    use IgnoredAttributesTestTrait;
    use MaxDepthTestTrait;
    use ObjectToPopulateTestTrait;
    use SkipNullValuesTestTrait;
    use SkipUninitializedValuesTestTrait;
    use TypeEnforcementTestTrait;

    private ObjectNormalizer $normalizer;
    private SerializerInterface&NormalizerInterface&MockObject $serializer;

    protected function setUp(): void
    {
        $this->createNormalizer();
    }

    private function createNormalizer(array $defaultContext = [], ?ClassMetadataFactoryInterface $classMetadataFactory = null): void
    {
        $this->serializer = $this->createMock(ObjectSerializerNormalizer::class);
        $this->normalizer = new ObjectNormalizer($classMetadataFactory, null, null, null, null, null, $defaultContext);
        $this->normalizer->setSerializer($this->serializer);
    }

    public function testNormalize()
    {
        $obj = new ObjectDummy();
        $object = new \stdClass();
        $obj->setFoo('foo');
        $obj->bar = 'bar';
        $obj->setBaz(true);
        $obj->setCamelCase('camelcase');
        $obj->setObject($object);
        $obj->setGo(true);

        $this->serializer
            ->expects($this->once())
            ->method('normalize')
            ->with($object, 'any')
            ->willReturn('string_object')
        ;

        $this->assertEquals(
            [
                'foo' => 'foo',
                'bar' => 'bar',
                'baz' => true,
                'fooBar' => 'foobar',
                'camelCase' => 'camelcase',
                'object' => 'string_object',
                'go' => true,
            ],
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testNormalizeObjectWithUninitializedProperties()
    {
        $obj = new Php74Dummy();
        $this->assertEquals(
            ['initializedProperty' => 'defaultValue'],
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testNormalizeObjectWithUnsetProperties()
    {
        $obj = new ObjectInner();
        unset($obj->foo);
        $this->assertEquals(
            ['bar' => null],
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testNormalizeObjectWithLazyProperties()
    {
        $obj = new LazyObjectInner();
        unset($obj->foo);
        $this->assertEquals(
            ['foo' => 123, 'bar' => null],
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testNormalizeObjectWithUninitializedPrivateProperties()
    {
        $obj = new Php74DummyPrivate();
        $this->assertEquals(
            ['initializedProperty' => 'defaultValue'],
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testNormalizeObjectWithPrivatePropertyWithoutGetter()
    {
        $obj = new DummyPrivatePropertyWithoutGetter();
        $this->assertEquals(
            ['bar' => 'bar'],
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testDenormalize()
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => 'bar', 'baz' => true, 'fooBar' => 'foobar'],
            ObjectDummy::class,
            'any'
        );
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
        $this->assertTrue($obj->isBaz());
    }

    public function testDenormalizeEmptyXmlArray()
    {
        $normalizer = $this->getDenormalizerForObjectToPopulate();
        $obj = $normalizer->denormalize(
            ['bar' => ''],
            ObjectDummy::class,
            'xml'
        );

        $this->assertIsArray($obj->bar);
        $this->assertEmpty($obj->bar);
    }

    public function testDenormalizeWithObject()
    {
        $data = new \stdClass();
        $data->foo = 'foo';
        $data->bar = 'bar';
        $data->fooBar = 'foobar';
        $obj = $this->normalizer->denormalize($data, ObjectDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
    }

    public function testDenormalizeNull()
    {
        $this->assertEquals(new ObjectDummy(), $this->normalizer->denormalize(null, ObjectDummy::class));
    }

    public function testConstructorDenormalize()
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => 'bar', 'baz' => true, 'fooBar' => 'foobar'],
            ObjectConstructorDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
        $this->assertTrue($obj->isBaz());
    }

    public function testConstructorDenormalizeWithNullArgument()
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => null, 'baz' => true],
            ObjectConstructorDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertNull($obj->bar);
        $this->assertTrue($obj->isBaz());
    }

    public function testConstructorDenormalizeWithMissingOptionalArgument()
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'test', 'baz' => [1, 2, 3]],
            ObjectConstructorOptionalArgsDummy::class, 'any');
        $this->assertEquals('test', $obj->getFoo());
        $this->assertEquals([], $obj->bar);
        $this->assertEquals([1, 2, 3], $obj->getBaz());
    }

    public function testConstructorDenormalizeWithOptionalDefaultArgument()
    {
        $obj = $this->normalizer->denormalize(
            ['bar' => 'test'],
            ObjectConstructorArgsWithDefaultValueDummy::class, 'any');
        $this->assertEquals([], $obj->getFoo());
        $this->assertEquals('test', $obj->getBar());
    }

    public function testConstructorWithObjectDenormalize()
    {
        $data = new \stdClass();
        $data->foo = 'foo';
        $data->bar = 'bar';
        $data->baz = true;
        $data->fooBar = 'foobar';
        $obj = $this->normalizer->denormalize($data, ObjectConstructorDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
    }

    public function testConstructorWithObjectDenormalizeUsingPropertyInfoExtractor()
    {
        $serializer = $this->createMock(ObjectSerializerNormalizer::class);
        $normalizer = new ObjectNormalizer(null, null, null, null, null, null, [], new PropertyInfoExtractor());
        $normalizer->setSerializer($serializer);

        $data = new \stdClass();
        $data->foo = 'foo';
        $data->bar = 'bar';
        $data->baz = true;
        $data->fooBar = 'foobar';
        $obj = $normalizer->denormalize($data, ObjectConstructorDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
    }

    public function testConstructorWithObjectTypeHintDenormalize()
    {
        $data = [
            'id' => 10,
            'inner' => [
                'foo' => 'oof',
                'bar' => 'rab',
            ],
        ];

        $normalizer = new ObjectNormalizer();
        $serializer = new Serializer([$normalizer]);
        $normalizer->setSerializer($serializer);

        $obj = $normalizer->denormalize($data, DummyWithConstructorObject::class);
        $this->assertInstanceOf(DummyWithConstructorObject::class, $obj);
        $this->assertEquals(10, $obj->getId());
        $this->assertInstanceOf(ObjectInner::class, $obj->getInner());
        $this->assertEquals('oof', $obj->getInner()->foo);
        $this->assertEquals('rab', $obj->getInner()->bar);
    }

    public function testConstructorWithUnconstructableNullableObjectTypeHintDenormalize()
    {
        $data = [
            'id' => 10,
            'inner' => null,
        ];

        $normalizer = new ObjectNormalizer();
        $serializer = new Serializer([$normalizer]);
        $normalizer->setSerializer($serializer);

        $obj = $normalizer->denormalize($data, DummyWithNullableConstructorObject::class);
        $this->assertInstanceOf(DummyWithNullableConstructorObject::class, $obj);
        $this->assertEquals(10, $obj->getId());
        $this->assertNull($obj->getInner());
    }

    public function testConstructorWithUnknownObjectTypeHintDenormalize()
    {
        $data = [
            'id' => 10,
            'unknown' => [
                'foo' => 'oof',
                'bar' => 'rab',
            ],
        ];

        $normalizer = new ObjectNormalizer();
        $serializer = new Serializer([$normalizer]);
        $normalizer->setSerializer($serializer);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not determine the class of the parameter "unknown".');

        $normalizer->denormalize($data, DummyWithConstructorInexistingObject::class);
    }

    // attributes

    protected function getNormalizerForAttributes(): ObjectNormalizer
    {
        $normalizer = new ObjectNormalizer();
        // instantiate a serializer with the normalizer to handle normalizing recursive structures
        new Serializer([$normalizer]);

        return $normalizer;
    }

    protected function getDenormalizerForAttributes(): ObjectNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory, null, null, new ReflectionExtractor());
        new Serializer([$normalizer]);

        return $normalizer;
    }

    protected function getNormalizerForFilterBool(): ObjectNormalizer
    {
        return new ObjectNormalizer();
    }

    public function testAttributesContextDenormalizeConstructor()
    {
        $normalizer = new ObjectNormalizer(null, null, null, new ReflectionExtractor());
        $serializer = new Serializer([$normalizer]);

        $objectInner = new ObjectInner();
        $objectInner->bar = 'bar';

        $obj = new DummyWithConstructorObjectAndDefaultValue('a', $objectInner);

        $context = ['attributes' => ['inner' => ['bar']]];
        $this->assertEquals($obj, $serializer->denormalize([
            'foo' => 'b',
            'inner' => ['foo' => 'foo', 'bar' => 'bar'],
        ], DummyWithConstructorObjectAndDefaultValue::class, null, $context));
    }

    public function testNormalizeSameObjectWithDifferentAttributes()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $this->normalizer = new ObjectNormalizer($classMetadataFactory);
        $serializer = new Serializer([$this->normalizer]);
        $this->normalizer->setSerializer($serializer);

        $dummy = new ObjectOuter();
        $dummy->foo = new ObjectInner();
        $dummy->foo->foo = 'foo.foo';
        $dummy->foo->bar = 'foo.bar';

        $dummy->bar = new ObjectInner();
        $dummy->bar->foo = 'bar.foo';
        $dummy->bar->bar = 'bar.bar';

        $this->assertEquals([
            'foo' => [
                'bar' => 'foo.bar',
            ],
            'bar' => [
                'foo' => 'bar.foo',
            ],
        ], $this->normalizer->normalize($dummy, 'json', [
            'attributes' => [
                'foo' => ['bar'],
                'bar' => ['foo'],
            ],
        ]));
    }

    // callbacks

    protected function getNormalizerForCallbacks(): ObjectNormalizer
    {
        return new ObjectNormalizer();
    }

    protected function getNormalizerForCallbacksWithPropertyTypeExtractor(): ObjectNormalizer
    {
        return new ObjectNormalizer(null, null, null, $this->getCallbackPropertyTypeExtractor());
    }

    // circular reference

    protected function getNormalizerForCircularReference(array $defaultContext): ObjectNormalizer
    {
        $normalizer = new ObjectNormalizer(null, null, null, null, null, null, $defaultContext);
        new Serializer([$normalizer]);

        return $normalizer;
    }

    protected function getSelfReferencingModel()
    {
        return new CircularReferenceDummy();
    }

    public function testSiblingReference()
    {
        $serializer = new Serializer([$this->normalizer]);
        $this->normalizer->setSerializer($serializer);

        $siblingHolder = new SiblingHolder();

        $expected = [
            'sibling0' => ['coopTilleuls' => 'Les-Tilleuls.coop'],
            'sibling1' => ['coopTilleuls' => 'Les-Tilleuls.coop'],
            'sibling2' => ['coopTilleuls' => 'Les-Tilleuls.coop'],
        ];
        $this->assertEquals($expected, $this->normalizer->normalize($siblingHolder));
    }

    // constructor arguments

    protected function getDenormalizerForConstructArguments(): ObjectNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $denormalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));
        $serializer = new Serializer([$denormalizer]);
        $denormalizer->setSerializer($serializer);

        return $denormalizer;
    }

    // groups

    protected function getNormalizerForGroups(): ObjectNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory);
        // instantiate a serializer with the normalizer to handle normalizing recursive structures
        new Serializer([$normalizer]);

        return $normalizer;
    }

    protected function getDenormalizerForGroups(): ObjectNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        return new ObjectNormalizer($classMetadataFactory);
    }

    public function testGroupsNormalizeWithNameConverter()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $this->normalizer = new ObjectNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());
        $this->normalizer->setSerializer($this->serializer);

        $obj = new GroupDummy();
        $obj->setFooBar('@dunglas');
        $obj->setSymfony('@coopTilleuls');
        $obj->setCoopTilleuls('les-tilleuls.coop');

        $this->assertEquals(
            [
                'bar' => null,
                'foo_bar' => '@dunglas',
                'symfony' => '@coopTilleuls',
                'default' => null,
                'class_name' => null,
            ],
            $this->normalizer->normalize($obj, null, [ObjectNormalizer::GROUPS => ['name_converter']])
        );
    }

    public function testGroupsDenormalizeWithNameConverter()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $this->normalizer = new ObjectNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());
        $this->normalizer->setSerializer($this->serializer);

        $obj = new GroupDummy();
        $obj->setFooBar('@dunglas');
        $obj->setSymfony('@coopTilleuls');

        $this->assertEquals(
            $obj,
            $this->normalizer->denormalize([
                'bar' => null,
                'foo_bar' => '@dunglas',
                'symfony' => '@coopTilleuls',
                'coop_tilleuls' => 'les-tilleuls.coop',
            ], GroupDummy::class, null, [ObjectNormalizer::GROUPS => ['name_converter']])
        );
    }

    public function testGroupsDenormalizeWithMetaDataNameConverter()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $this->normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));
        $this->normalizer->setSerializer($this->serializer);

        $obj = new OtherSerializedNameDummy();
        $obj->setBuz('Aldrin');

        $this->assertEquals(
            $obj,
            $this->normalizer->denormalize([
                'buz' => 'Aldrin',
            ], 'Symfony\Component\Serializer\Tests\Fixtures\OtherSerializedNameDummy', null, [ObjectNormalizer::GROUPS => ['a']])
        );
    }

    // ignored attributes

    protected function getNormalizerForIgnoredAttributes(): ObjectNormalizer
    {
        $normalizer = new ObjectNormalizer();
        // instantiate a serializer with the normalizer to handle normalizing recursive structures
        new Serializer([$normalizer]);

        return $normalizer;
    }

    protected function getDenormalizerForIgnoredAttributes(): ObjectNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory, null, null, new ReflectionExtractor());
        new Serializer([$normalizer]);

        return $normalizer;
    }

    // max depth

    protected function getNormalizerForMaxDepth(): ObjectNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory);
        $serializer = new Serializer([$normalizer]);
        $normalizer->setSerializer($serializer);

        return $normalizer;
    }

    // object to populate

    protected function getDenormalizerForObjectToPopulate(): ObjectNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory, null, null, new PhpDocExtractor());
        new Serializer([$normalizer]);

        return $normalizer;
    }

    // skip null

    protected function getNormalizerForSkipNullValues(): ObjectNormalizer
    {
        return new ObjectNormalizer();
    }

    // skip uninitialized

    protected function getNormalizerForSkipUninitializedValues(): ObjectNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        return new ObjectNormalizer($classMetadataFactory);
    }

    protected function getObjectCollectionWithExpectedArray(): array
    {
        $typedPropsObject = new TypedPropertiesObject();
        $typedPropsObject->unInitialized = 'value2';

        $collection = [
            new TypedPropertiesObject(),
            $typedPropsObject,
            new TypedPropertiesObjectWithGetters(),
            (new TypedPropertiesObjectWithGetters())->setUninitialized('value2'),
        ];

        $expectedArrays = [
            ['initialized' => 'value', 'initialized2' => 'value'],
            ['unInitialized' => 'value2', 'initialized' => 'value', 'initialized2' => 'value'],
            ['initialized' => 'value', 'initialized2' => 'value'],
            ['unInitialized' => 'value2', 'initialized' => 'value', 'initialized2' => 'value'],
        ];

        return [$collection, $expectedArrays];
    }

    protected function getNormalizerForCacheableObjectAttributesTest(): ObjectNormalizer
    {
        return new ObjectNormalizer();
    }

    // type enforcement

    protected function getDenormalizerForTypeEnforcement(): ObjectNormalizer
    {
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new ObjectNormalizer(null, null, null, $extractor);
        new Serializer([new ArrayDenormalizer(), $normalizer]);

        return $normalizer;
    }

    public function testUnableToNormalizeObjectAttribute()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $this->normalizer->setSerializer($serializer);

        $obj = new ObjectDummy();
        $object = new \stdClass();
        $obj->setObject($object);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot normalize attribute "object" because the injected serializer is not a normalizer');

        $this->normalizer->normalize($obj, 'any');
    }

    public function testDenormalizeNonExistingAttribute()
    {
        $this->assertEquals(
            new ObjectDummy(),
            $this->normalizer->denormalize(['non_existing' => true], ObjectDummy::class)
        );
    }

    public function testNoTraversableSupport()
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \ArrayObject()));
    }

    public function testNormalizeStatic()
    {
        $this->assertEquals(['foo' => 'K'], $this->normalizer->normalize(new ObjectWithStaticPropertiesAndMethods()));
    }

    public function testNormalizeUpperCaseAttributes()
    {
        $this->assertEquals(['Foo' => 'Foo', 'Bar' => 'BarBar'], $this->normalizer->normalize(new ObjectWithUpperCaseAttributeNames()));
    }

    public function testNormalizeNotSerializableContext()
    {
        $objectDummy = new ObjectDummy();
        $expected = [
            'foo' => null,
            'baz' => null,
            'fooBar' => '',
            'camelCase' => null,
            'object' => null,
            'bar' => null,
            'go' => null,
        ];

        $this->assertEquals($expected, $this->normalizer->normalize($objectDummy, null, ['not_serializable' => function () {
        }]));
    }

    public function testThrowUnexpectedValueException()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->normalizer->denormalize(['foo' => 'bar'], ObjectTypeHinted::class);
    }

    public function testDenomalizeRecursive()
    {
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new ObjectNormalizer(null, null, null, $extractor);
        $serializer = new Serializer([new ArrayDenormalizer(), new DateTimeNormalizer(), $normalizer]);

        $obj = $serializer->denormalize([
            'inner' => ['foo' => 'foo', 'bar' => 'bar'],
            'date' => '1988/01/21',
            'inners' => [['foo' => 1], ['foo' => 2]],
        ], ObjectOuter::class);

        $this->assertSame('foo', $obj->getInner()->foo);
        $this->assertSame('bar', $obj->getInner()->bar);
        $this->assertSame('1988-01-21', $obj->getDate()->format('Y-m-d'));
        $this->assertSame(1, $obj->getInners()[0]->foo);
        $this->assertSame(2, $obj->getInners()[1]->foo);
    }

    public function testAcceptJsonNumber()
    {
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new ObjectNormalizer(null, null, null, $extractor);
        $serializer = new Serializer([new ArrayDenormalizer(), new DateTimeNormalizer(), $normalizer]);

        $this->assertSame(10.0, $serializer->denormalize(['number' => 10], JsonNumber::class, 'json')->number);
        $this->assertSame(10.0, $serializer->denormalize(['number' => 10], JsonNumber::class, 'jsonld')->number);
    }

    public function testDoesntHaveIssuesWithUnionConstTypes()
    {
        if (!class_exists(PhpStanExtractor::class) || !class_exists(PhpDocParser::class)) {
            $this->markTestSkipped('phpstan/phpdoc-parser required for this test');
        }

        $extractor = new PropertyInfoExtractor([], [new PhpStanExtractor(), new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new ObjectNormalizer(null, null, null, $extractor);
        $serializer = new Serializer([new ArrayDenormalizer(), new DateTimeNormalizer(), $normalizer]);

        $this->assertSame('bar', $serializer->denormalize(['foo' => 'bar'], (new class() {
            /** @var self::*|null */
            public $foo;
        })::class)->foo);
    }

    public function testExtractAttributesRespectsContext()
    {
        $normalizer = new ObjectNormalizer();

        $data = new ObjectDummy();
        $data->setFoo('bar');
        $data->bar = 'foo';

        $this->assertSame(['foo' => 'bar', 'bar' => 'foo'], $normalizer->normalize($data, null, [AbstractNormalizer::ATTRIBUTES => ['foo', 'bar']]));
    }

    public function testDenormalizeFalsePseudoType()
    {
        // given a serializer that extracts the attribute types of an object via ReflectionExtractor
        $propertyTypeExtractor = new PropertyInfoExtractor([], [new ReflectionExtractor()], [], [], []);
        $objectNormalizer = new ObjectNormalizer(null, null, null, $propertyTypeExtractor);

        $serializer = new Serializer([$objectNormalizer]);

        // when denormalizing some data into an object where an attribute uses the false pseudo type
        /** @var Php80Dummy $object */
        $object = $serializer->denormalize(['canBeFalseOrString' => false], Php80Dummy::class);

        // then the attribute that declared false was filled correctly
        $this->assertFalse($object->canBeFalseOrString);
    }

    public function testAdvancedNameConverter()
    {
        $nameConverter = new class() implements AdvancedNameConverterInterface {
            public function normalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
            {
                return \sprintf('%s-%s-%s-%s', $propertyName, $class, $format, $context['foo']);
            }

            public function denormalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
            {
                return \sprintf('%s-%s-%s-%s', $propertyName, $class, $format, $context['foo']);
            }
        };

        $normalizer = new ObjectNormalizer(null, $nameConverter);
        $this->assertArrayHasKey('foo-Symfony\Component\Serializer\Tests\Normalizer\Features\ObjectDummy-json-bar', $normalizer->normalize(new ObjectDummy(), 'json', ['foo' => 'bar']));
    }

    public function testDefaultObjectClassResolver()
    {
        $normalizer = new ObjectNormalizer();

        $obj = new ObjectDummy();
        $obj->setFoo('foo');
        $obj->bar = 'bar';
        $obj->setBaz(true);
        $obj->setCamelCase('camelcase');
        $obj->unwantedProperty = 'notwanted';
        $obj->setGo(false);

        $this->assertEquals(
            [
                'foo' => 'foo',
                'bar' => 'bar',
                'baz' => true,
                'fooBar' => 'foobar',
                'camelCase' => 'camelcase',
                'object' => null,
                'go' => false,
            ],
            $normalizer->normalize($obj, 'any')
        );
    }

    public function testObjectClassResolver()
    {
        $classResolver = fn ($object) => ObjectDummy::class;

        $normalizer = new ObjectNormalizer(null, null, null, null, null, $classResolver);

        $obj = new ProxyObjectDummy();
        $obj->setFoo('foo');
        $obj->bar = 'bar';
        $obj->setBaz(true);
        $obj->setCamelCase('camelcase');
        $obj->unwantedProperty = 'notwanted';

        $this->assertEquals(
            [
                'foo' => 'foo',
                'bar' => 'bar',
                'baz' => true,
                'fooBar' => 'foobar',
                'camelCase' => 'camelcase',
                'object' => null,
                'go' => null,
            ],
            $normalizer->normalize($obj, 'any')
        );
    }

    public function testNormalizeStdClass()
    {
        $o1 = new \stdClass();
        $o1->foo = 'f';
        $o1->bar = 'b';

        $this->assertSame(['foo' => 'f', 'bar' => 'b'], $this->normalizer->normalize($o1));

        $o2 = new \stdClass();
        $o2->baz = 'baz';

        $this->assertSame(['baz' => 'baz'], $this->normalizer->normalize($o2));
    }

    public function testNotNormalizableValueInvalidType()
    {
        if (!class_exists(InvalidTypeException::class)) {
            $this->markTestSkipped('Skipping test as the improvements on PropertyAccess are required.');
        }

        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Expected argument of type "string", "array" given at property path "initialized"');

        try {
            $this->normalizer->denormalize(['initialized' => ['not a string']], TypedPropertiesObject::class, 'array');
        } catch (NotNormalizableValueException $e) {
            $this->assertSame(['string'], $e->getExpectedTypes());

            throw $e;
        }
    }

    public function testNormalizeWithoutSerializerSet()
    {
        $normalizer = new ObjectNormalizer(new ClassMetadataFactory(new AttributeLoader()));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot normalize attribute "foo" because the injected serializer is not a normalizer.');

        $normalizer->normalize(new ObjectConstructorDummy([], [], []));
    }

    public function testNormalizeWithIgnoreAttributeAndPrivateProperties()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory);

        $this->assertSame(['foo' => 'foo'], $normalizer->normalize(new ObjectDummyWithIgnoreAttributeAndPrivateProperty()));
    }

    public function testDenormalizeWithIgnoreAttributeAndPrivateProperties()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory);

        $obj = $normalizer->denormalize([
            'foo' => 'set',
            'ignore' => 'set',
            'private' => 'set',
        ], ObjectDummyWithIgnoreAttributeAndPrivateProperty::class);

        $expected = new ObjectDummyWithIgnoreAttributeAndPrivateProperty();
        $expected->foo = 'set';

        $this->assertEquals($expected, $obj);
    }

    public function testNormalizeWithPropertyPath()
    {
        $classMetadataFactory = new ClassMetadataFactory(new YamlFileLoader(__DIR__.'/../Fixtures/property-path-mapping.yaml'));
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));

        $dummyInner = new ObjectInner();
        $dummyInner->foo = 'foo';
        $dummy = new ObjectOuter();
        $dummy->setInner($dummyInner);

        $this->assertSame(['inner_foo' => 'foo'], $normalizer->normalize($dummy, 'json', ['groups' => 'read']));
    }

    public function testDenormalizeWithPropertyPath()
    {
        $classMetadataFactory = new ClassMetadataFactory(new YamlFileLoader(__DIR__.'/../Fixtures/property-path-mapping.yaml'));
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));

        $dummy = new ObjectOuter();
        $dummy->setInner(new ObjectInner());

        $obj = $normalizer->denormalize(['inner_foo' => 'foo'], ObjectOuter::class, 'json', [
            'object_to_populate' => $dummy,
            'groups' => 'read',
        ]);

        $expectedInner = new ObjectInner();
        $expectedInner->foo = 'foo';
        $expected = new ObjectOuter();
        $expected->setInner($expectedInner);

        $this->assertEquals($expected, $obj);
    }
}

class ProxyObjectDummy extends ObjectDummy
{
    public $unwantedProperty;
}

class ObjectConstructorDummy
{
    protected $foo;
    public $bar;
    private $baz;

    public function __construct($foo, $bar, $baz)
    {
        $this->foo = $foo;
        $this->bar = $bar;
        $this->baz = $baz;
    }

    public function getFoo()
    {
        return $this->foo;
    }

    public function isBaz()
    {
        return $this->baz;
    }

    public function otherMethod()
    {
        throw new \RuntimeException('Dummy::otherMethod() should not be called');
    }
}

abstract class ObjectSerializerNormalizer implements SerializerInterface, NormalizerInterface
{
}

class ObjectConstructorOptionalArgsDummy
{
    protected $foo;
    public $bar;
    private $baz;

    public function __construct($foo, $bar = [], $baz = [])
    {
        $this->foo = $foo;
        $this->bar = $bar;
        $this->baz = $baz;
    }

    public function getFoo()
    {
        return $this->foo;
    }

    public function getBaz()
    {
        return $this->baz;
    }

    public function otherMethod()
    {
        throw new \RuntimeException('Dummy::otherMethod() should not be called');
    }
}

class ObjectConstructorArgsWithDefaultValueDummy
{
    protected $foo;
    protected $bar;

    public function __construct($foo = [], $bar = null)
    {
        $this->foo = $foo;
        $this->bar = $bar;
    }

    public function getFoo()
    {
        return $this->foo;
    }

    public function getBar()
    {
        return $this->bar;
    }

    public function otherMethod()
    {
        throw new \RuntimeException('Dummy::otherMethod() should not be called');
    }
}

class ObjectWithStaticPropertiesAndMethods
{
    public $foo = 'K';
    public static $bar = 'A';

    public static function getBaz()
    {
        return 'L';
    }
}

class ObjectTypeHinted
{
    public function setFoo(array $f)
    {
    }
}

class ObjectOuter
{
    public $foo;
    public $bar;
    /**
     * @var ObjectInner
     */
    private $inner;
    private $date;

    /**
     * @var ObjectInner[]
     */
    private $inners;

    public function getInner()
    {
        return $this->inner;
    }

    public function setInner(ObjectInner $inner)
    {
        $this->inner = $inner;
    }

    public function setDate(\DateTimeInterface $date)
    {
        $this->date = $date;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function setInners(array $inners)
    {
        $this->inners = $inners;
    }

    public function getInners()
    {
        return $this->inners;
    }
}

class ObjectInner
{
    public $foo;
    public $bar;
}

class LazyObjectInner extends ObjectInner
{
    public function __get($name)
    {
        if ('foo' === $name) {
            return $this->foo = 123;
        }
    }

    public function __isset($name): bool
    {
        return 'foo' === $name;
    }
}

class DummyWithConstructorObject
{
    private $id;
    private $inner;

    public function __construct($id, ObjectInner $inner)
    {
        $this->id = $id;
        $this->inner = $inner;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getInner()
    {
        return $this->inner;
    }
}

class DummyWithConstructorInexistingObject
{
    public function __construct($id, Unknown $unknown)
    {
    }
}

class JsonNumber
{
    /**
     * @var float
     */
    public $number;
}

class DummyWithConstructorObjectAndDefaultValue
{
    private $foo;
    private $inner;

    public function __construct($foo = 'a', ?ObjectInner $inner = null)
    {
        $this->foo = $foo;
        $this->inner = $inner;
    }

    public function getFoo()
    {
        return $this->foo;
    }

    public function getInner()
    {
        return $this->inner;
    }
}

class ObjectWithUpperCaseAttributeNames
{
    private $Foo = 'Foo';
    public $Bar = 'BarBar';

    public function getFoo()
    {
        return $this->Foo;
    }
}

class DummyWithNullableConstructorObject
{
    private $id;
    private $inner;

    public function __construct($id, ?ObjectConstructorDummy $inner)
    {
        $this->id = $id;
        $this->inner = $inner;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getInner()
    {
        return $this->inner;
    }
}

class ObjectDummyWithIgnoreAttributeAndPrivateProperty
{
    public $foo = 'foo';

    #[Ignore]
    public $ignored = 'ignored';

    private $private = 'private';
}
