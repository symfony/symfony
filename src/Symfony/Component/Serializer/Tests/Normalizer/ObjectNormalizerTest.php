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
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\CircularReferenceDummy;
use Symfony\Component\Serializer\Tests\Fixtures\GroupDummy;
use Symfony\Component\Serializer\Tests\Fixtures\MaxDepthDummy;
use Symfony\Component\Serializer\Tests\Fixtures\SiblingHolder;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ObjectNormalizerTest extends TestCase
{
    /**
     * @var ObjectNormalizer
     */
    private $normalizer;
    /**
     * @var SerializerInterface
     */
    private $serializer;

    protected function setUp()
    {
        $this->serializer = $this->getMockBuilder(__NAMESPACE__.'\ObjectSerializerNormalizer')->getMock();
        $this->normalizer = new ObjectNormalizer();
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

        $this->serializer
            ->expects($this->once())
            ->method('normalize')
            ->with($object, 'any')
            ->will($this->returnValue('string_object'))
        ;

        $this->assertEquals(
            array(
                'foo' => 'foo',
                'bar' => 'bar',
                'baz' => true,
                'fooBar' => 'foobar',
                'camelCase' => 'camelcase',
                'object' => 'string_object',
            ),
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testDenormalize()
    {
        $obj = $this->normalizer->denormalize(
            array('foo' => 'foo', 'bar' => 'bar', 'baz' => true, 'fooBar' => 'foobar'),
            __NAMESPACE__.'\ObjectDummy',
            'any'
        );
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
        $this->assertTrue($obj->isBaz());
    }

    public function testDenormalizeWithObject()
    {
        $data = new \stdClass();
        $data->foo = 'foo';
        $data->bar = 'bar';
        $data->fooBar = 'foobar';
        $obj = $this->normalizer->denormalize($data, __NAMESPACE__.'\ObjectDummy', 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
    }

    public function testDenormalizeNull()
    {
        $this->assertEquals(new ObjectDummy(), $this->normalizer->denormalize(null, __NAMESPACE__.'\ObjectDummy'));
    }

    public function testConstructorDenormalize()
    {
        $obj = $this->normalizer->denormalize(
            array('foo' => 'foo', 'bar' => 'bar', 'baz' => true, 'fooBar' => 'foobar'),
            __NAMESPACE__.'\ObjectConstructorDummy', 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
        $this->assertTrue($obj->isBaz());
    }

    public function testConstructorDenormalizeWithNullArgument()
    {
        $obj = $this->normalizer->denormalize(
            array('foo' => 'foo', 'bar' => null, 'baz' => true),
            __NAMESPACE__.'\ObjectConstructorDummy', 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertNull($obj->bar);
        $this->assertTrue($obj->isBaz());
    }

    public function testConstructorDenormalizeWithMissingOptionalArgument()
    {
        $obj = $this->normalizer->denormalize(
            array('foo' => 'test', 'baz' => array(1, 2, 3)),
            __NAMESPACE__.'\ObjectConstructorOptionalArgsDummy', 'any');
        $this->assertEquals('test', $obj->getFoo());
        $this->assertEquals(array(), $obj->bar);
        $this->assertEquals(array(1, 2, 3), $obj->getBaz());
    }

    public function testConstructorDenormalizeWithOptionalDefaultArgument()
    {
        $obj = $this->normalizer->denormalize(
            array('bar' => 'test'),
            __NAMESPACE__.'\ObjectConstructorArgsWithDefaultValueDummy', 'any');
        $this->assertEquals(array(), $obj->getFoo());
        $this->assertEquals('test', $obj->getBar());
    }

    public function testConstructorWithObjectDenormalize()
    {
        $data = new \stdClass();
        $data->foo = 'foo';
        $data->bar = 'bar';
        $data->baz = true;
        $data->fooBar = 'foobar';
        $obj = $this->normalizer->denormalize($data, __NAMESPACE__.'\ObjectConstructorDummy', 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
    }

    public function testConstructorWithObjectTypeHintDenormalize()
    {
        $data = array(
            'id' => 10,
            'inner' => array(
                'foo' => 'oof',
                'bar' => 'rab',
            ),
        );

        $normalizer = new ObjectNormalizer();
        $serializer = new Serializer(array($normalizer));
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
        $data = array(
            'id' => 10,
            'inner' => null,
        );

        $normalizer = new ObjectNormalizer();
        $serializer = new Serializer(array($normalizer));
        $normalizer->setSerializer($serializer);

        $obj = $normalizer->denormalize($data, DummyWithNullableConstructorObject::class);
        $this->assertInstanceOf(DummyWithNullableConstructorObject::class, $obj);
        $this->assertEquals(10, $obj->getId());
        $this->assertNull($obj->getInner());
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\RuntimeException
     * @expectedExceptionMessage Could not determine the class of the parameter "unknown".
     */
    public function testConstructorWithUnknownObjectTypeHintDenormalize()
    {
        $data = array(
            'id' => 10,
            'unknown' => array(
                'foo' => 'oof',
                'bar' => 'rab',
            ),
        );

        $normalizer = new ObjectNormalizer();
        $serializer = new Serializer(array($normalizer));
        $normalizer->setSerializer($serializer);

        $normalizer->denormalize($data, DummyWithConstructorInexistingObject::class);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException
     * @expectedExceptionMessage Cannot create an instance of Symfony\Component\Serializer\Tests\Normalizer\DummyValueObject from serialized data because its constructor requires parameter "bar" to be present.
     */
    public function testConstructorWithMissingData()
    {
        $data = array(
            'foo' => 10,
        );

        $normalizer = new ObjectNormalizer();

        $normalizer->denormalize($data, DummyValueObject::class);
    }

    public function testFillWithEmptyDataWhenMissingData()
    {
        $data = array(
            'foo' => 10,
        );

        $normalizer = new ObjectNormalizer();

        $result = $normalizer->denormalize($data, DummyValueObject::class, 'json', array(
            'default_constructor_arguments' => array(
                DummyValueObject::class => array('foo' => '', 'bar' => ''),
            ),
        ));

        $this->assertEquals(new DummyValueObject(10, ''), $result);
    }

    public function testGroupsNormalize()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new ObjectNormalizer($classMetadataFactory);
        $this->normalizer->setSerializer($this->serializer);

        $obj = new GroupDummy();
        $obj->setFoo('foo');
        $obj->setBar('bar');
        $obj->setFooBar('fooBar');
        $obj->setSymfony('symfony');
        $obj->setKevin('kevin');
        $obj->setCoopTilleuls('coopTilleuls');

        $this->assertEquals(array(
            'bar' => 'bar',
        ), $this->normalizer->normalize($obj, null, array(ObjectNormalizer::GROUPS => array('c'))));

        $this->assertEquals(array(
            'symfony' => 'symfony',
            'foo' => 'foo',
            'fooBar' => 'fooBar',
            'bar' => 'bar',
            'kevin' => 'kevin',
            'coopTilleuls' => 'coopTilleuls',
        ), $this->normalizer->normalize($obj, null, array(ObjectNormalizer::GROUPS => array('a', 'c'))));
    }

    public function testGroupsDenormalize()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new ObjectNormalizer($classMetadataFactory);
        $this->normalizer->setSerializer($this->serializer);

        $obj = new GroupDummy();
        $obj->setFoo('foo');

        $toNormalize = array('foo' => 'foo', 'bar' => 'bar');

        $normalized = $this->normalizer->denormalize(
            $toNormalize,
            'Symfony\Component\Serializer\Tests\Fixtures\GroupDummy',
            null,
            array(ObjectNormalizer::GROUPS => array('a'))
        );
        $this->assertEquals($obj, $normalized);

        $obj->setBar('bar');

        $normalized = $this->normalizer->denormalize(
            $toNormalize,
            'Symfony\Component\Serializer\Tests\Fixtures\GroupDummy',
            null,
            array(ObjectNormalizer::GROUPS => array('a', 'b'))
        );
        $this->assertEquals($obj, $normalized);
    }

    public function testNormalizeNoPropertyInGroup()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new ObjectNormalizer($classMetadataFactory);
        $this->normalizer->setSerializer($this->serializer);

        $obj = new GroupDummy();
        $obj->setFoo('foo');

        $this->assertEquals(array(), $this->normalizer->normalize($obj, null, array('groups' => array('notExist'))));
    }

    public function testGroupsNormalizeWithNameConverter()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new ObjectNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());
        $this->normalizer->setSerializer($this->serializer);

        $obj = new GroupDummy();
        $obj->setFooBar('@dunglas');
        $obj->setSymfony('@coopTilleuls');
        $obj->setCoopTilleuls('les-tilleuls.coop');

        $this->assertEquals(
            array(
                'bar' => null,
                'foo_bar' => '@dunglas',
                'symfony' => '@coopTilleuls',
            ),
            $this->normalizer->normalize($obj, null, array(ObjectNormalizer::GROUPS => array('name_converter')))
        );
    }

    public function testGroupsDenormalizeWithNameConverter()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new ObjectNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());
        $this->normalizer->setSerializer($this->serializer);

        $obj = new GroupDummy();
        $obj->setFooBar('@dunglas');
        $obj->setSymfony('@coopTilleuls');

        $this->assertEquals(
            $obj,
            $this->normalizer->denormalize(array(
                'bar' => null,
                'foo_bar' => '@dunglas',
                'symfony' => '@coopTilleuls',
                'coop_tilleuls' => 'les-tilleuls.coop',
            ), 'Symfony\Component\Serializer\Tests\Fixtures\GroupDummy', null, array(ObjectNormalizer::GROUPS => array('name_converter')))
        );
    }

    /**
     * @dataProvider provideCallbacks
     */
    public function testCallbacks($callbacks, $value, $result, $message)
    {
        $this->normalizer->setCallbacks($callbacks);

        $obj = new ObjectConstructorDummy('', $value, true);

        $this->assertEquals(
            $result,
            $this->normalizer->normalize($obj, 'any'),
            $message
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testUncallableCallbacks()
    {
        $this->normalizer->setCallbacks(array('bar' => null));

        $obj = new ObjectConstructorDummy('baz', 'quux', true);

        $this->normalizer->normalize($obj, 'any');
    }

    public function testIgnoredAttributes()
    {
        $this->normalizer->setIgnoredAttributes(array('foo', 'bar', 'baz', 'camelCase', 'object'));

        $obj = new ObjectDummy();
        $obj->setFoo('foo');
        $obj->bar = 'bar';
        $obj->setBaz(true);

        $this->assertEquals(
            array('fooBar' => 'foobar'),
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testIgnoredAttributesDenormalize()
    {
        $this->normalizer->setIgnoredAttributes(array('fooBar', 'bar', 'baz'));

        $obj = new ObjectDummy();
        $obj->setFoo('foo');

        $this->assertEquals(
            $obj,
            $this->normalizer->denormalize(array('fooBar' => 'fooBar', 'foo' => 'foo', 'baz' => 'baz'), __NAMESPACE__.'\ObjectDummy')
        );
    }

    public function provideCallbacks()
    {
        return array(
            array(
                array(
                    'bar' => function ($bar) {
                        $this->assertEquals('baz', $bar);

                        return 'baz';
                    },
                ),
                'baz',
                array('foo' => '', 'bar' => 'baz', 'baz' => true),
                'Change a string',
            ),
            array(
                array(
                    'bar' => function ($value, $object, $attributeName, $format, $context) {
                        $this->assertSame('baz', $value);
                        $this->assertInstanceOf(ObjectConstructorDummy::class, $object);
                        $this->assertSame('bar', $attributeName);
                        $this->assertSame('any', $format);
                        $this->assertArrayHasKey('circular_reference_limit', $context);
                    },
                ),
                'baz',
                array('foo' => '', 'bar' => null, 'baz' => true),
                'Null an item',
            ),
            array(
                array(
                    'bar' => function ($bar) {
                        return $bar->format('d-m-Y H:i:s');
                    },
                ),
                new \DateTime('2011-09-10 06:30:00'),
                array('foo' => '', 'bar' => '10-09-2011 06:30:00', 'baz' => true),
                'Format a date',
            ),
            array(
                array(
                    'bar' => function ($bars) {
                        $foos = '';
                        foreach ($bars as $bar) {
                            $foos .= $bar->getFoo();
                        }

                        return $foos;
                    },
                ),
                array(new ObjectConstructorDummy('baz', '', false), new ObjectConstructorDummy('quux', '', false)),
                array('foo' => '', 'bar' => 'bazquux', 'baz' => true),
                'Collect a property',
            ),
            array(
                array(
                    'bar' => function ($bars) {
                        return \count($bars);
                    },
                ),
                array(new ObjectConstructorDummy('baz', '', false), new ObjectConstructorDummy('quux', '', false)),
                array('foo' => '', 'bar' => 2, 'baz' => true),
                'Count a property',
            ),
        );
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\LogicException
     * @expectedExceptionMessage Cannot normalize attribute "object" because the injected serializer is not a normalizer
     */
    public function testUnableToNormalizeObjectAttribute()
    {
        $serializer = $this->getMockBuilder('Symfony\Component\Serializer\SerializerInterface')->getMock();
        $this->normalizer->setSerializer($serializer);

        $obj = new ObjectDummy();
        $object = new \stdClass();
        $obj->setObject($object);

        $this->normalizer->normalize($obj, 'any');
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\CircularReferenceException
     */
    public function testUnableToNormalizeCircularReference()
    {
        $serializer = new Serializer(array($this->normalizer));
        $this->normalizer->setSerializer($serializer);
        $this->normalizer->setCircularReferenceLimit(2);

        $obj = new CircularReferenceDummy();

        $this->normalizer->normalize($obj);
    }

    public function testSiblingReference()
    {
        $serializer = new Serializer(array($this->normalizer));
        $this->normalizer->setSerializer($serializer);

        $siblingHolder = new SiblingHolder();

        $expected = array(
            'sibling0' => array('coopTilleuls' => 'Les-Tilleuls.coop'),
            'sibling1' => array('coopTilleuls' => 'Les-Tilleuls.coop'),
            'sibling2' => array('coopTilleuls' => 'Les-Tilleuls.coop'),
        );
        $this->assertEquals($expected, $this->normalizer->normalize($siblingHolder));
    }

    public function testCircularReferenceHandler()
    {
        $serializer = new Serializer(array($this->normalizer));
        $this->normalizer->setSerializer($serializer);
        $this->normalizer->setCircularReferenceHandler(function ($obj) {
            return \get_class($obj);
        });

        $obj = new CircularReferenceDummy();

        $expected = array('me' => 'Symfony\Component\Serializer\Tests\Fixtures\CircularReferenceDummy');
        $this->assertEquals($expected, $this->normalizer->normalize($obj));
    }

    public function testDenormalizeNonExistingAttribute()
    {
        $this->assertEquals(
            new ObjectDummy(),
            $this->normalizer->denormalize(array('non_existing' => true), __NAMESPACE__.'\ObjectDummy')
        );
    }

    public function testNoTraversableSupport()
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \ArrayObject()));
    }

    public function testNormalizeStatic()
    {
        $this->assertEquals(array('foo' => 'K'), $this->normalizer->normalize(new ObjectWithStaticPropertiesAndMethods()));
    }

    public function testNormalizeUpperCaseAttributes()
    {
        $this->assertEquals(array('Foo' => 'Foo', 'Bar' => 'BarBar'), $this->normalizer->normalize(new ObjectWithUpperCaseAttributeNames()));
    }

    public function testNormalizeNotSerializableContext()
    {
        $objectDummy = new ObjectDummy();
        $expected = array(
            'foo' => null,
            'baz' => null,
            'fooBar' => '',
            'camelCase' => null,
            'object' => null,
            'bar' => null,
        );

        $this->assertEquals($expected, $this->normalizer->normalize($objectDummy, null, array('not_serializable' => function () {
        })));
    }

    public function testMaxDepth()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new ObjectNormalizer($classMetadataFactory);
        $serializer = new Serializer(array($this->normalizer));
        $this->normalizer->setSerializer($serializer);

        $level1 = new MaxDepthDummy();
        $level1->foo = 'level1';

        $level2 = new MaxDepthDummy();
        $level2->foo = 'level2';
        $level1->child = $level2;

        $level3 = new MaxDepthDummy();
        $level3->foo = 'level3';
        $level2->child = $level3;

        $result = $serializer->normalize($level1, null, array(ObjectNormalizer::ENABLE_MAX_DEPTH => true));

        $expected = array(
            'bar' => null,
            'foo' => 'level1',
            'child' => array(
                    'bar' => null,
                    'foo' => 'level2',
                    'child' => array(
                            'bar' => null,
                            'child' => null,
                        ),
                ),
        );

        $this->assertEquals($expected, $result);

        $expected = array(
            'bar' => null,
            'foo' => 'level1',
            'child' => array(
                'bar' => null,
                'foo' => 'level2',
                'child' => array(
                    'bar' => null,
                    'child' => null,
                    'foo' => 'handler',
                ),
            ),
        );

        $this->normalizer->setMaxDepthHandler(function ($obj) {
            return 'handler';
        });

        $result = $serializer->normalize($level1, null, array(ObjectNormalizer::ENABLE_MAX_DEPTH => true));
        $this->assertEquals($expected, $result);

        $this->normalizer->setMaxDepthHandler(function ($object, $parentObject, $attributeName, $format, $context) {
            $this->assertSame('level3', $object);
            $this->assertInstanceOf(MaxDepthDummy::class, $parentObject);
            $this->assertSame('foo', $attributeName);
            $this->assertSame('test', $format);
            $this->assertArrayHasKey(ObjectNormalizer::ENABLE_MAX_DEPTH, $context);

            return 'handler';
        });

        $serializer->normalize($level1, 'test', array(ObjectNormalizer::ENABLE_MAX_DEPTH => true));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testThrowUnexpectedValueException()
    {
        $this->normalizer->denormalize(array('foo' => 'bar'), ObjectTypeHinted::class);
    }

    public function testDenomalizeRecursive()
    {
        $extractor = new PropertyInfoExtractor(array(), array(new PhpDocExtractor(), new ReflectionExtractor()));
        $normalizer = new ObjectNormalizer(null, null, null, $extractor);
        $serializer = new Serializer(array(new ArrayDenormalizer(), new DateTimeNormalizer(), $normalizer));

        $obj = $serializer->denormalize(array(
            'inner' => array('foo' => 'foo', 'bar' => 'bar'),
            'date' => '1988/01/21',
            'inners' => array(array('foo' => 1), array('foo' => 2)),
        ), ObjectOuter::class);

        $this->assertSame('foo', $obj->getInner()->foo);
        $this->assertSame('bar', $obj->getInner()->bar);
        $this->assertSame('1988-01-21', $obj->getDate()->format('Y-m-d'));
        $this->assertSame(1, $obj->getInners()[0]->foo);
        $this->assertSame(2, $obj->getInners()[1]->foo);
    }

    public function testAcceptJsonNumber()
    {
        $extractor = new PropertyInfoExtractor(array(), array(new PhpDocExtractor(), new ReflectionExtractor()));
        $normalizer = new ObjectNormalizer(null, null, null, $extractor);
        $serializer = new Serializer(array(new ArrayDenormalizer(), new DateTimeNormalizer(), $normalizer));

        $this->assertSame(10.0, $serializer->denormalize(array('number' => 10), JsonNumber::class, 'json')->number);
        $this->assertSame(10.0, $serializer->denormalize(array('number' => 10), JsonNumber::class, 'jsonld')->number);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     * @expectedExceptionMessage The type of the "date" attribute for class "Symfony\Component\Serializer\Tests\Normalizer\ObjectOuter" must be one of "DateTimeInterface" ("string" given).
     */
    public function testRejectInvalidType()
    {
        $normalizer = new ObjectNormalizer(null, null, null, new ReflectionExtractor());
        $serializer = new Serializer(array($normalizer));

        $serializer->denormalize(array('date' => 'foo'), ObjectOuter::class);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     * @expectedExceptionMessage The type of the key "a" must be "int" ("string" given).
     */
    public function testRejectInvalidKey()
    {
        $extractor = new PropertyInfoExtractor(array(), array(new PhpDocExtractor(), new ReflectionExtractor()));
        $normalizer = new ObjectNormalizer(null, null, null, $extractor);
        $serializer = new Serializer(array(new ArrayDenormalizer(), new DateTimeNormalizer(), $normalizer));

        $serializer->denormalize(array('inners' => array('a' => array('foo' => 1))), ObjectOuter::class);
    }

    public function testDoNotRejectInvalidTypeOnDisableTypeEnforcementContextOption()
    {
        $extractor = new PropertyInfoExtractor(array(), array(new PhpDocExtractor()));
        $normalizer = new ObjectNormalizer(null, null, null, $extractor);
        $serializer = new Serializer(array($normalizer));
        $context = array(ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true);

        $this->assertSame('foo', $serializer->denormalize(array('number' => 'foo'), JsonNumber::class, null, $context)->number);
    }

    public function testExtractAttributesRespectsFormat()
    {
        $normalizer = new FormatAndContextAwareNormalizer();

        $data = new ObjectDummy();
        $data->setFoo('bar');
        $data->bar = 'foo';

        $this->assertSame(array('foo' => 'bar', 'bar' => 'foo'), $normalizer->normalize($data, 'foo_and_bar_included'));
    }

    public function testExtractAttributesRespectsContext()
    {
        $normalizer = new FormatAndContextAwareNormalizer();

        $data = new ObjectDummy();
        $data->setFoo('bar');
        $data->bar = 'foo';

        $this->assertSame(array('foo' => 'bar', 'bar' => 'foo'), $normalizer->normalize($data, null, array('include_foo_and_bar' => true)));
    }

    public function testAttributesContextNormalize()
    {
        $normalizer = new ObjectNormalizer();
        $serializer = new Serializer(array($normalizer));

        $objectInner = new ObjectInner();
        $objectInner->foo = 'innerFoo';
        $objectInner->bar = 'innerBar';

        $objectDummy = new ObjectDummy();
        $objectDummy->setFoo('foo');
        $objectDummy->setBaz(true);
        $objectDummy->setObject($objectInner);

        $context = array('attributes' => array('foo', 'baz', 'object' => array('foo')));
        $this->assertEquals(
            array(
                'foo' => 'foo',
                'baz' => true,
                'object' => array('foo' => 'innerFoo'),
            ),
            $serializer->normalize($objectDummy, null, $context)
        );

        $context = array('attributes' => array('foo', 'baz', 'object'));
        $this->assertEquals(
            array(
                'foo' => 'foo',
                'baz' => true,
                'object' => array('foo' => 'innerFoo', 'bar' => 'innerBar'),
            ),
            $serializer->normalize($objectDummy, null, $context)
        );
    }

    public function testAttributesContextDenormalize()
    {
        $normalizer = new ObjectNormalizer(null, null, null, new ReflectionExtractor());
        $serializer = new Serializer(array($normalizer));

        $objectInner = new ObjectInner();
        $objectInner->foo = 'innerFoo';

        $objectOuter = new ObjectOuter();
        $objectOuter->bar = 'bar';
        $objectOuter->setInner($objectInner);

        $context = array('attributes' => array('bar', 'inner' => array('foo')));
        $this->assertEquals($objectOuter, $serializer->denormalize(
            array(
                'foo' => 'foo',
                'bar' => 'bar',
                'date' => '2017-02-03',
                'inner' => array('foo' => 'innerFoo', 'bar' => 'innerBar'),
            ), ObjectOuter::class, null, $context));
    }

    public function testAttributesContextDenormalizeConstructor()
    {
        $normalizer = new ObjectNormalizer(null, null, null, new ReflectionExtractor());
        $serializer = new Serializer(array($normalizer));

        $objectInner = new ObjectInner();
        $objectInner->bar = 'bar';

        $obj = new DummyWithConstructorObjectAndDefaultValue('a', $objectInner);

        $context = array('attributes' => array('inner' => array('bar')));
        $this->assertEquals($obj, $serializer->denormalize(array(
            'foo' => 'b',
            'inner' => array('foo' => 'foo', 'bar' => 'bar'),
        ), DummyWithConstructorObjectAndDefaultValue::class, null, $context));
    }

    public function testNormalizeSameObjectWithDifferentAttributes()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new ObjectNormalizer($classMetadataFactory);
        $serializer = new Serializer(array($this->normalizer));
        $this->normalizer->setSerializer($serializer);

        $dummy = new ObjectOuter();
        $dummy->foo = new ObjectInner();
        $dummy->foo->foo = 'foo.foo';
        $dummy->foo->bar = 'foo.bar';

        $dummy->bar = new ObjectInner();
        $dummy->bar->foo = 'bar.foo';
        $dummy->bar->bar = 'bar.bar';

        $this->assertEquals(array(
            'foo' => array(
                'bar' => 'foo.bar',
            ),
            'bar' => array(
                'foo' => 'bar.foo',
            ),
        ), $this->normalizer->normalize($dummy, 'json', array(
            'attributes' => array(
                'foo' => array('bar'),
                'bar' => array('foo'),
            ),
        )));
    }
}

class ObjectDummy
{
    protected $foo;
    public $bar;
    private $baz;
    protected $camelCase;
    protected $object;

    public function getFoo()
    {
        return $this->foo;
    }

    public function setFoo($foo)
    {
        $this->foo = $foo;
    }

    public function isBaz()
    {
        return $this->baz;
    }

    public function setBaz($baz)
    {
        $this->baz = $baz;
    }

    public function getFooBar()
    {
        return $this->foo.$this->bar;
    }

    public function getCamelCase()
    {
        return $this->camelCase;
    }

    public function setCamelCase($camelCase)
    {
        $this->camelCase = $camelCase;
    }

    public function otherMethod()
    {
        throw new \RuntimeException('Dummy::otherMethod() should not be called');
    }

    public function setObject($object)
    {
        $this->object = $object;
    }

    public function getObject()
    {
        return $this->object;
    }
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

    public function __construct($foo, $bar = array(), $baz = array())
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

    public function __construct($foo = array(), $bar)
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

class FormatAndContextAwareNormalizer extends ObjectNormalizer
{
    protected function isAllowedAttribute($classOrObject, $attribute, $format = null, array $context = array())
    {
        if (\in_array($attribute, array('foo', 'bar')) && 'foo_and_bar_included' === $format) {
            return true;
        }

        if (\in_array($attribute, array('foo', 'bar')) && isset($context['include_foo_and_bar']) && true === $context['include_foo_and_bar']) {
            return true;
        }

        return false;
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
class DummyValueObject
{
    private $foo;
    private $bar;

    public function __construct($foo, $bar)
    {
        $this->foo = $foo;
        $this->bar = $bar;
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

    public function __construct($foo = 'a', ObjectInner $inner)
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
