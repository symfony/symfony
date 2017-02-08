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
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\GroupDummy;
use Symfony\Component\Serializer\Tests\Fixtures\PropertyCircularReferenceDummy;
use Symfony\Component\Serializer\Tests\Fixtures\PropertySiblingHolder;

class PropertyNormalizerTest extends TestCase
{
    /**
     * @var PropertyNormalizer
     */
    private $normalizer;
    /**
     * @var SerializerInterface
     */
    private $serializer;

    protected function setUp()
    {
        $this->serializer = $this->getMockBuilder('Symfony\Component\Serializer\SerializerInterface')->getMock();
        $this->normalizer = new PropertyNormalizer();
        $this->normalizer->setSerializer($this->serializer);
    }

    public function testNormalize()
    {
        $obj = new PropertyDummy();
        $obj->foo = 'foo';
        $obj->setBar('bar');
        $obj->setCamelCase('camelcase');
        $this->assertEquals(
            array('foo' => 'foo', 'bar' => 'bar', 'camelCase' => 'camelcase'),
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testDenormalize()
    {
        $obj = $this->normalizer->denormalize(
            array('foo' => 'foo', 'bar' => 'bar'),
            __NAMESPACE__.'\PropertyDummy',
            'any'
        );
        $this->assertEquals('foo', $obj->foo);
        $this->assertEquals('bar', $obj->getBar());
    }

    /**
     * @group legacy
     */
    public function testLegacyDenormalizeOnCamelCaseFormat()
    {
        $this->normalizer->setCamelizedAttributes(array('camel_case'));
        $obj = $this->normalizer->denormalize(
            array('camel_case' => 'value'),
            __NAMESPACE__.'\PropertyDummy'
        );
        $this->assertEquals('value', $obj->getCamelCase());
    }

    /**
     * @group legacy
     */
    public function testLegacyCamelizedAttributesNormalize()
    {
        $obj = new PropertyCamelizedDummy('dunglas.fr');
        $obj->fooBar = 'les-tilleuls.coop';
        $obj->bar_foo = 'lostinthesupermarket.fr';

        $this->normalizer->setCamelizedAttributes(array('kevin_dunglas'));
        $this->assertEquals($this->normalizer->normalize($obj), array(
            'kevin_dunglas' => 'dunglas.fr',
            'fooBar' => 'les-tilleuls.coop',
            'bar_foo' => 'lostinthesupermarket.fr',
        ));

        $this->normalizer->setCamelizedAttributes(array('foo_bar'));
        $this->assertEquals($this->normalizer->normalize($obj), array(
            'kevinDunglas' => 'dunglas.fr',
            'foo_bar' => 'les-tilleuls.coop',
            'bar_foo' => 'lostinthesupermarket.fr',
        ));
    }

    /**
     * @group legacy
     */
    public function testLegacyCamelizedAttributesDenormalize()
    {
        $obj = new PropertyCamelizedDummy('dunglas.fr');
        $obj->fooBar = 'les-tilleuls.coop';
        $obj->bar_foo = 'lostinthesupermarket.fr';

        $this->normalizer->setCamelizedAttributes(array('kevin_dunglas'));
        $this->assertEquals($this->normalizer->denormalize(array(
            'kevin_dunglas' => 'dunglas.fr',
            'fooBar' => 'les-tilleuls.coop',
            'bar_foo' => 'lostinthesupermarket.fr',
        ), __NAMESPACE__.'\PropertyCamelizedDummy'), $obj);

        $this->normalizer->setCamelizedAttributes(array('foo_bar'));
        $this->assertEquals($this->normalizer->denormalize(array(
            'kevinDunglas' => 'dunglas.fr',
            'foo_bar' => 'les-tilleuls.coop',
            'bar_foo' => 'lostinthesupermarket.fr',
        ), __NAMESPACE__.'\PropertyCamelizedDummy'), $obj);
    }

    public function testNameConverterSupport()
    {
        $this->normalizer = new PropertyNormalizer(null, new CamelCaseToSnakeCaseNameConverter());
        $obj = $this->normalizer->denormalize(
            array('camel_case' => 'camelCase'),
            __NAMESPACE__.'\PropertyDummy'
        );
        $this->assertEquals('camelCase', $obj->getCamelCase());
    }

    public function testConstructorDenormalize()
    {
        $obj = $this->normalizer->denormalize(
            array('foo' => 'foo', 'bar' => 'bar'),
            __NAMESPACE__.'\PropertyConstructorDummy',
            'any'
        );
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
    }

    public function testConstructorDenormalizeWithNullArgument()
    {
        $obj = $this->normalizer->denormalize(
            array('foo' => null, 'bar' => 'bar'),
            __NAMESPACE__.'\PropertyConstructorDummy', '
            any'
        );
        $this->assertNull($obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
    }

    /**
     * @dataProvider provideCallbacks
     */
    public function testCallbacks($callbacks, $value, $result, $message)
    {
        $this->normalizer->setCallbacks($callbacks);

        $obj = new PropertyConstructorDummy('', $value);

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

        $obj = new PropertyConstructorDummy('baz', 'quux');

        $this->normalizer->normalize($obj, 'any');
    }

    public function testIgnoredAttributes()
    {
        $this->normalizer->setIgnoredAttributes(array('foo', 'bar', 'camelCase'));

        $obj = new PropertyDummy();
        $obj->foo = 'foo';
        $obj->setBar('bar');

        $this->assertEquals(
            array(),
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testGroupsNormalize()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new PropertyNormalizer($classMetadataFactory);
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
        ), $this->normalizer->normalize($obj, null, array(PropertyNormalizer::GROUPS => array('c'))));

        // The PropertyNormalizer is not able to hydrate properties from parent classes
        $this->assertEquals(array(
            'symfony' => 'symfony',
            'foo' => 'foo',
            'fooBar' => 'fooBar',
            'bar' => 'bar',
        ), $this->normalizer->normalize($obj, null, array(PropertyNormalizer::GROUPS => array('a', 'c'))));
    }

    public function testGroupsDenormalize()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new PropertyNormalizer($classMetadataFactory);
        $this->normalizer->setSerializer($this->serializer);

        $obj = new GroupDummy();
        $obj->setFoo('foo');

        $toNormalize = array('foo' => 'foo', 'bar' => 'bar');

        $normalized = $this->normalizer->denormalize(
            $toNormalize,
            'Symfony\Component\Serializer\Tests\Fixtures\GroupDummy',
            null,
            array(PropertyNormalizer::GROUPS => array('a'))
        );
        $this->assertEquals($obj, $normalized);

        $obj->setBar('bar');

        $normalized = $this->normalizer->denormalize(
            $toNormalize,
            'Symfony\Component\Serializer\Tests\Fixtures\GroupDummy',
            null,
            array(PropertyNormalizer::GROUPS => array('a', 'b'))
        );
        $this->assertEquals($obj, $normalized);
    }

    public function testGroupsNormalizeWithNameConverter()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new PropertyNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());
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
            $this->normalizer->normalize($obj, null, array(PropertyNormalizer::GROUPS => array('name_converter')))
        );
    }

    public function testGroupsDenormalizeWithNameConverter()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new PropertyNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());
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
            ), 'Symfony\Component\Serializer\Tests\Fixtures\GroupDummy', null, array(PropertyNormalizer::GROUPS => array('name_converter')))
        );
    }

    public function provideCallbacks()
    {
        return array(
            array(
                array(
                    'bar' => function ($bar) {
                        return 'baz';
                    },
                ),
                'baz',
                array('foo' => '', 'bar' => 'baz'),
                'Change a string',
            ),
            array(
                array(
                    'bar' => function ($bar) {
                        return;
                    },
                ),
                'baz',
                array('foo' => '', 'bar' => null),
                'Null an item',
            ),
            array(
                array(
                    'bar' => function ($bar) {
                        return $bar->format('d-m-Y H:i:s');
                    },
                ),
                new \DateTime('2011-09-10 06:30:00'),
                array('foo' => '', 'bar' => '10-09-2011 06:30:00'),
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
                array(new PropertyConstructorDummy('baz', ''), new PropertyConstructorDummy('quux', '')),
                array('foo' => '', 'bar' => 'bazquux'),
                'Collect a property',
            ),
            array(
                array(
                    'bar' => function ($bars) {
                        return count($bars);
                    },
                ),
                array(new PropertyConstructorDummy('baz', ''), new PropertyConstructorDummy('quux', '')),
                array('foo' => '', 'bar' => 2),
                'Count a property',
            ),
        );
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\CircularReferenceException
     */
    public function testUnableToNormalizeCircularReference()
    {
        $serializer = new Serializer(array($this->normalizer));
        $this->normalizer->setSerializer($serializer);
        $this->normalizer->setCircularReferenceLimit(2);

        $obj = new PropertyCircularReferenceDummy();

        $this->normalizer->normalize($obj);
    }

    public function testSiblingReference()
    {
        $serializer = new Serializer(array($this->normalizer));
        $this->normalizer->setSerializer($serializer);

        $siblingHolder = new PropertySiblingHolder();

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
            return get_class($obj);
        });

        $obj = new PropertyCircularReferenceDummy();

        $expected = array('me' => 'Symfony\Component\Serializer\Tests\Fixtures\PropertyCircularReferenceDummy');
        $this->assertEquals($expected, $this->normalizer->normalize($obj));
    }

    public function testDenormalizeNonExistingAttribute()
    {
        $this->assertEquals(
            new PropertyDummy(),
            $this->normalizer->denormalize(array('non_existing' => true), __NAMESPACE__.'\PropertyDummy')
        );
    }

    public function testDenormalizeShouldIgnoreStaticProperty()
    {
        $obj = $this->normalizer->denormalize(array('outOfScope' => true), __NAMESPACE__.'\PropertyDummy');

        $this->assertEquals(new PropertyDummy(), $obj);
        $this->assertEquals('out_of_scope', PropertyDummy::$outOfScope);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\LogicException
     * @expectedExceptionMessage Cannot normalize attribute "bar" because injected serializer is not a normalizer
     */
    public function testUnableToNormalizeObjectAttribute()
    {
        $serializer = $this->getMockBuilder('Symfony\Component\Serializer\SerializerInterface')->getMock();
        $this->normalizer->setSerializer($serializer);

        $obj = new PropertyDummy();
        $object = new \stdClass();
        $obj->setBar($object);

        $this->normalizer->normalize($obj, 'any');
    }

    public function testNoTraversableSupport()
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \ArrayObject()));
    }

    public function testNoStaticPropertySupport()
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new StaticPropertyDummy()));
    }
}

class PropertyDummy
{
    public static $outOfScope = 'out_of_scope';
    public $foo;
    private $bar;
    protected $camelCase;

    public function getBar()
    {
        return $this->bar;
    }

    public function setBar($bar)
    {
        $this->bar = $bar;
    }

    public function getCamelCase()
    {
        return $this->camelCase;
    }

    public function setCamelCase($camelCase)
    {
        $this->camelCase = $camelCase;
    }
}

class PropertyConstructorDummy
{
    protected $foo;
    private $bar;

    public function __construct($foo, $bar)
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
}

class PropertyCamelizedDummy
{
    private $kevinDunglas;
    public $fooBar;
    public $bar_foo;

    public function __construct($kevinDunglas = null)
    {
        $this->kevinDunglas = $kevinDunglas;
    }
}

class StaticPropertyDummy
{
    private static $property = 'value';
}
