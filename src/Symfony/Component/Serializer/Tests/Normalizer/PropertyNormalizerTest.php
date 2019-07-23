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
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\GroupDummy;
use Symfony\Component\Serializer\Tests\Fixtures\GroupDummyChild;
use Symfony\Component\Serializer\Tests\Fixtures\PropertyCircularReferenceDummy;
use Symfony\Component\Serializer\Tests\Fixtures\PropertySiblingHolder;
use Symfony\Component\Serializer\Tests\Normalizer\Features\CallbacksObject;
use Symfony\Component\Serializer\Tests\Normalizer\Features\CallbacksTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\CircularReferenceTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\ConstructorArgumentsTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\GroupsTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\IgnoredAttributesTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\MaxDepthTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\ObjectToPopulateTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\TypeEnforcementTestTrait;

class PropertyNormalizerTest extends TestCase
{
    use CallbacksTestTrait;
    use CircularReferenceTestTrait;
    use ConstructorArgumentsTestTrait;
    use GroupsTestTrait;
    use IgnoredAttributesTestTrait;
    use MaxDepthTestTrait;
    use ObjectToPopulateTestTrait;
    use TypeEnforcementTestTrait;

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
        $this->createNormalizer();
    }

    private function createNormalizer(array $defaultContext = [])
    {
        $this->serializer = $this->getMockBuilder('Symfony\Component\Serializer\SerializerInterface')->getMock();
        $this->normalizer = new PropertyNormalizer(null, null, null, null, null, $defaultContext);
        $this->normalizer->setSerializer($this->serializer);
    }

    public function testNormalize()
    {
        $obj = new PropertyDummy();
        $obj->foo = 'foo';
        $obj->setBar('bar');
        $obj->setCamelCase('camelcase');
        $this->assertEquals(
            ['foo' => 'foo', 'bar' => 'bar', 'camelCase' => 'camelcase'],
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testDenormalize()
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => 'bar'],
            __NAMESPACE__.'\PropertyDummy',
            'any'
        );
        $this->assertEquals('foo', $obj->foo);
        $this->assertEquals('bar', $obj->getBar());
    }

    public function testNormalizeWithParentClass()
    {
        $group = new GroupDummyChild();
        $group->setBaz('baz');
        $group->setFoo('foo');
        $group->setBar('bar');
        $group->setKevin('Kevin');
        $group->setCoopTilleuls('coop');
        $this->assertEquals(
            ['foo' => 'foo', 'bar' => 'bar', 'kevin' => 'Kevin', 'coopTilleuls' => 'coop', 'fooBar' => null, 'symfony' => null, 'baz' => 'baz'],
            $this->normalizer->normalize($group, 'any')
        );
    }

    public function testDenormalizeWithParentClass()
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => 'bar', 'kevin' => 'Kevin', 'baz' => 'baz'],
            GroupDummyChild::class,
            'any'
        );
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
        $this->assertEquals('Kevin', $obj->getKevin());
        $this->assertEquals('baz', $obj->getBaz());
        $this->assertNull($obj->getSymfony());
    }

    public function testConstructorDenormalize()
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => 'bar'],
            __NAMESPACE__.'\PropertyConstructorDummy',
            'any'
        );
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
    }

    public function testConstructorDenormalizeWithNullArgument()
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => null, 'bar' => 'bar'],
            __NAMESPACE__.'\PropertyConstructorDummy', '
            any'
        );
        $this->assertNull($obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
    }

    protected function getNormalizerForCallbacks(): PropertyNormalizer
    {
        return new PropertyNormalizer();
    }

    /**
     * @dataProvider provideCallbacks
     */
    public function testLegacyCallbacks($callbacks, $value, $result)
    {
        $this->normalizer->setCallbacks($callbacks);

        $obj = new CallbacksObject($value);

        $this->assertEquals(
            $result,
            $this->normalizer->normalize($obj, 'any')
        );
    }

    /**
     * @dataProvider provideInvalidCallbacks
     */
    public function testLegacyUncallableCallbacks($callbacks)
    {
        $this->expectException(InvalidArgumentException::class);

        $this->normalizer->setCallbacks($callbacks);
    }

    protected function getNormalizerForCircularReference(): PropertyNormalizer
    {
        $normalizer = new PropertyNormalizer();
        new Serializer([$normalizer]);

        return $normalizer;
    }

    protected function getSelfReferencingModel()
    {
        return new PropertyCircularReferenceDummy();
    }

    public function testLegacyUnableToNormalizeCircularReference()
    {
        $this->normalizer->setCircularReferenceLimit(2);
        new Serializer([$this->normalizer]);

        $obj = new PropertyCircularReferenceDummy();

        $this->expectException(CircularReferenceException::class);
        $this->normalizer->normalize($obj);
    }

    public function testSiblingReference()
    {
        $serializer = new Serializer([$this->normalizer]);
        $this->normalizer->setSerializer($serializer);

        $siblingHolder = new PropertySiblingHolder();

        $expected = [
            'sibling0' => ['coopTilleuls' => 'Les-Tilleuls.coop'],
            'sibling1' => ['coopTilleuls' => 'Les-Tilleuls.coop'],
            'sibling2' => ['coopTilleuls' => 'Les-Tilleuls.coop'],
        ];
        $this->assertEquals($expected, $this->normalizer->normalize($siblingHolder));
    }

    public function testLegacyCircularReferenceHandler()
    {
        $this->normalizer->setCircularReferenceHandler(function ($obj) {
            return \get_class($obj);
        });

        new Serializer([$this->normalizer]);

        $obj = new PropertyCircularReferenceDummy();

        $expected = ['me' => PropertyCircularReferenceDummy::class];
        $this->assertEquals($expected, $this->normalizer->normalize($obj));
    }

    protected function getDenormalizerForConstructArguments(): PropertyNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $denormalizer = new PropertyNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));
        $serializer = new Serializer([$denormalizer]);
        $denormalizer->setSerializer($serializer);

        return $denormalizer;
    }

    protected function getNormalizerForGroups(): PropertyNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        return new PropertyNormalizer($classMetadataFactory);
    }

    protected function getDenormalizerForGroups(): PropertyNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        return new PropertyNormalizer($classMetadataFactory);
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
            [
                'bar' => null,
                'foo_bar' => '@dunglas',
                'symfony' => '@coopTilleuls',
            ],
            $this->normalizer->normalize($obj, null, [PropertyNormalizer::GROUPS => ['name_converter']])
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
            $this->normalizer->denormalize([
                'bar' => null,
                'foo_bar' => '@dunglas',
                'symfony' => '@coopTilleuls',
                'coop_tilleuls' => 'les-tilleuls.coop',
            ], 'Symfony\Component\Serializer\Tests\Fixtures\GroupDummy', null, [PropertyNormalizer::GROUPS => ['name_converter']])
        );
    }

    protected function getDenormalizerForIgnoredAttributes(): PropertyNormalizer
    {
        $normalizer = new PropertyNormalizer();
        // instantiate a serializer with the normalizer to handle normalizing recursive structures
        new Serializer([$normalizer]);

        return $normalizer;
    }

    protected function getNormalizerForIgnoredAttributes(): PropertyNormalizer
    {
        $normalizer = new PropertyNormalizer();
        // instantiate a serializer with the normalizer to handle normalizing recursive structures
        new Serializer([$normalizer]);

        return $normalizer;
    }

    public function testIgnoredAttributesContextDenormalizeInherit()
    {
        $this->markTestSkipped('This has not been tested previously - did not manage to make the test work');
    }

    public function testLegacyIgnoredAttributes()
    {
        $ignoredAttributes = ['foo', 'bar', 'camelCase'];
        $this->normalizer->setIgnoredAttributes($ignoredAttributes);

        $obj = new PropertyDummy();
        $obj->foo = 'foo';
        $obj->setBar('bar');

        $this->assertEquals(
            [],
            $this->normalizer->normalize($obj, 'any')
        );
    }

    protected function getNormalizerForMaxDepth(): PropertyNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new PropertyNormalizer($classMetadataFactory);
        $serializer = new Serializer([$normalizer]);
        $normalizer->setSerializer($serializer);

        return $normalizer;
    }

    protected function getDenormalizerForObjectToPopulate(): PropertyNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new PropertyNormalizer($classMetadataFactory, null, new PhpDocExtractor());
        new Serializer([$normalizer]);

        return $normalizer;
    }

    protected function getDenormalizerForTypeEnforcement(): DenormalizerInterface
    {
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new PropertyNormalizer(null, null, $extractor);
        $serializer = new Serializer([new ArrayDenormalizer(), $normalizer]);
        $normalizer->setSerializer($serializer);

        return $normalizer;
    }

    public function testDenormalizeNonExistingAttribute()
    {
        $this->assertEquals(
            new PropertyDummy(),
            $this->normalizer->denormalize(['non_existing' => true], __NAMESPACE__.'\PropertyDummy')
        );
    }

    public function testDenormalizeShouldIgnoreStaticProperty()
    {
        $obj = $this->normalizer->denormalize(['outOfScope' => true], __NAMESPACE__.'\PropertyDummy');

        $this->assertEquals(new PropertyDummy(), $obj);
        $this->assertEquals('out_of_scope', PropertyDummy::$outOfScope);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\LogicException
     * @expectedExceptionMessage Cannot normalize attribute "bar" because the injected serializer is not a normalizer
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

    public function testInheritedPropertiesSupport()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new PropertyChildDummy()));
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

class StaticPropertyDummy
{
    private static $property = 'value';
}

class PropertyParentDummy
{
    private $foo = 'bar';
}

class PropertyChildDummy extends PropertyParentDummy
{
}
