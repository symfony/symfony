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
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\CircularReferenceDummy;
use Symfony\Component\Serializer\Tests\Fixtures\GroupDummy;
use Symfony\Component\Serializer\Tests\Fixtures\SiblingHolder;
use Symfony\Component\Serializer\Tests\Normalizer\Features\CallbacksTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\CircularReferenceTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\ConstructorArgumentsTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\GroupsTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\IgnoredAttributesTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\MaxDepthTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\ObjectToPopulateTestTrait;
use Symfony\Component\Serializer\Tests\Normalizer\Features\TypeEnforcementTestTrait;

class GetSetMethodNormalizerTest extends TestCase
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
     * @var GetSetMethodNormalizer
     */
    private $normalizer;
    /**
     * @var SerializerInterface
     */
    private $serializer;

    protected function setUp(): void
    {
        $this->createNormalizer();
    }

    private function createNormalizer(array $defaultContext = [])
    {
        $this->serializer = $this->getMockBuilder(__NAMESPACE__.'\SerializerNormalizer')->getMock();
        $this->normalizer = new GetSetMethodNormalizer(null, null, null, null, null, $defaultContext);
        $this->normalizer->setSerializer($this->serializer);
    }

    public function testInterface()
    {
        $this->assertInstanceOf('Symfony\Component\Serializer\Normalizer\NormalizerInterface', $this->normalizer);
        $this->assertInstanceOf('Symfony\Component\Serializer\Normalizer\DenormalizerInterface', $this->normalizer);
    }

    public function testNormalize()
    {
        $obj = new GetSetDummy();
        $object = new \stdClass();
        $obj->setFoo('foo');
        $obj->setBar('bar');
        $obj->setBaz(true);
        $obj->setCamelCase('camelcase');
        $obj->setObject($object);

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
            ],
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testDenormalize()
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => 'bar', 'baz' => true, 'fooBar' => 'foobar'],
            GetSetDummy::class,
            'any'
        );
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
        $this->assertTrue($obj->isBaz());
    }

    public function testIgnoredAttributesInContext()
    {
        $ignoredAttributes = ['foo', 'bar', 'baz', 'object'];
        $obj = new GetSetDummy();
        $obj->setFoo('foo');
        $obj->setBar('bar');
        $obj->setCamelCase(true);
        $this->assertEquals(
            [
                'fooBar' => 'foobar',
                'camelCase' => true,
            ],
            $this->normalizer->normalize($obj, 'any', [AbstractNormalizer::IGNORED_ATTRIBUTES => $ignoredAttributes])
        );
    }

    public function testDenormalizeWithObject()
    {
        $data = new \stdClass();
        $data->foo = 'foo';
        $data->bar = 'bar';
        $data->fooBar = 'foobar';
        $obj = $this->normalizer->denormalize($data, GetSetDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
    }

    public function testDenormalizeNull()
    {
        $this->assertEquals(new GetSetDummy(), $this->normalizer->denormalize(null, GetSetDummy::class));
    }

    public function testConstructorDenormalize()
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => 'bar', 'baz' => true, 'fooBar' => 'foobar'],
            __NAMESPACE__.'\GetConstructorDummy', 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
        $this->assertTrue($obj->isBaz());
    }

    public function testConstructorDenormalizeWithNullArgument()
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'foo', 'bar' => null, 'baz' => true],
            __NAMESPACE__.'\GetConstructorDummy', 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertNull($obj->getBar());
        $this->assertTrue($obj->isBaz());
    }

    public function testConstructorDenormalizeWithMissingOptionalArgument()
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => 'test', 'baz' => [1, 2, 3]],
            __NAMESPACE__.'\GetConstructorOptionalArgsDummy', 'any');
        $this->assertEquals('test', $obj->getFoo());
        $this->assertEquals([], $obj->getBar());
        $this->assertEquals([1, 2, 3], $obj->getBaz());
    }

    public function testConstructorDenormalizeWithOptionalDefaultArgument()
    {
        $obj = $this->normalizer->denormalize(
            ['bar' => 'test'],
            __NAMESPACE__.'\GetConstructorArgsWithDefaultValueDummy', 'any');
        $this->assertEquals([], $obj->getFoo());
        $this->assertEquals('test', $obj->getBar());
    }

    public function testConstructorDenormalizeWithVariadicArgument()
    {
        $obj = $this->normalizer->denormalize(
            ['foo' => [1, 2, 3]],
            'Symfony\Component\Serializer\Tests\Fixtures\VariadicConstructorArgsDummy', 'any');
        $this->assertEquals([1, 2, 3], $obj->getFoo());
    }

    public function testConstructorDenormalizeWithMissingVariadicArgument()
    {
        $obj = $this->normalizer->denormalize(
            [],
            'Symfony\Component\Serializer\Tests\Fixtures\VariadicConstructorArgsDummy', 'any');
        $this->assertEquals([], $obj->getFoo());
    }

    public function testConstructorWithObjectDenormalize()
    {
        $data = new \stdClass();
        $data->foo = 'foo';
        $data->bar = 'bar';
        $data->baz = true;
        $data->fooBar = 'foobar';
        $obj = $this->normalizer->denormalize($data, __NAMESPACE__.'\GetConstructorDummy', 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
    }

    public function testConstructorWArgWithPrivateMutator()
    {
        $obj = $this->normalizer->denormalize(['foo' => 'bar'], __NAMESPACE__.'\ObjectConstructorArgsWithPrivateMutatorDummy', 'any');
        $this->assertEquals('bar', $obj->getFoo());
    }

    protected function getNormalizerForCallbacks(): GetSetMethodNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        return new GetSetMethodNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));
    }

    protected function getNormalizerForCircularReference(): GetSetMethodNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new GetSetMethodNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));
        new Serializer([$normalizer]);

        return $normalizer;
    }

    protected function getSelfReferencingModel()
    {
        return new CircularReferenceDummy();
    }

    protected function getDenormalizerForConstructArguments(): GetSetMethodNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $denormalizer = new GetSetMethodNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));
        new Serializer([$denormalizer]);

        return $denormalizer;
    }

    protected function getNormalizerForGroups(): GetSetMethodNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        return new GetSetMethodNormalizer($classMetadataFactory);
    }

    protected function getDenormalizerForGroups(): GetSetMethodNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        return new GetSetMethodNormalizer($classMetadataFactory);
    }

    public function testGroupsNormalizeWithNameConverter()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new GetSetMethodNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());
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
            $this->normalizer->normalize($obj, null, [GetSetMethodNormalizer::GROUPS => ['name_converter']])
        );
    }

    public function testGroupsDenormalizeWithNameConverter()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $this->normalizer = new GetSetMethodNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());
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
            ], 'Symfony\Component\Serializer\Tests\Fixtures\GroupDummy', null, [GetSetMethodNormalizer::GROUPS => ['name_converter']])
        );
    }

    protected function getNormalizerForMaxDepth(): NormalizerInterface
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new GetSetMethodNormalizer($classMetadataFactory);
        $serializer = new Serializer([$normalizer]);
        $normalizer->setSerializer($serializer);

        return $normalizer;
    }

    protected function getDenormalizerForObjectToPopulate(): DenormalizerInterface
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new GetSetMethodNormalizer($classMetadataFactory, null, new PhpDocExtractor());
        new Serializer([$normalizer]);

        return $normalizer;
    }

    protected function getDenormalizerForTypeEnforcement(): DenormalizerInterface
    {
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new GetSetMethodNormalizer(null, null, $extractor);
        $serializer = new Serializer([new ArrayDenormalizer(), $normalizer]);
        $normalizer->setSerializer($serializer);

        return $normalizer;
    }

    public function testRejectInvalidKey()
    {
        $this->markTestSkipped('This test makes no sense with the GetSetMethodNormalizer');
    }

    protected function getNormalizerForIgnoredAttributes(): GetSetMethodNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new GetSetMethodNormalizer($classMetadataFactory, null, new PhpDocExtractor());
        new Serializer([$normalizer]);

        return $normalizer;
    }

    protected function getDenormalizerForIgnoredAttributes(): GetSetMethodNormalizer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new GetSetMethodNormalizer($classMetadataFactory, null, new PhpDocExtractor());
        new Serializer([$normalizer]);

        return $normalizer;
    }

    public function testUnableToNormalizeObjectAttribute()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\LogicException');
        $this->expectExceptionMessage('Cannot normalize attribute "object" because the injected serializer is not a normalizer');
        $serializer = $this->getMockBuilder('Symfony\Component\Serializer\SerializerInterface')->getMock();
        $this->normalizer->setSerializer($serializer);

        $obj = new GetSetDummy();
        $object = new \stdClass();
        $obj->setObject($object);

        $this->normalizer->normalize($obj, 'any');
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

    public function testDenormalizeNonExistingAttribute()
    {
        $this->assertEquals(
            new GetSetDummy(),
            $this->normalizer->denormalize(['non_existing' => true], GetSetDummy::class)
        );
    }

    public function testDenormalizeShouldNotSetStaticAttribute()
    {
        $obj = $this->normalizer->denormalize(['staticObject' => true], GetSetDummy::class);

        $this->assertEquals(new GetSetDummy(), $obj);
        $this->assertNull(GetSetDummy::getStaticObject());
    }

    public function testNoTraversableSupport()
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \ArrayObject()));
    }

    public function testNoStaticGetSetSupport()
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new ObjectWithJustStaticSetterDummy()));
    }

    public function testPrivateSetter()
    {
        $obj = $this->normalizer->denormalize(['foo' => 'foobar'], __NAMESPACE__.'\ObjectWithPrivateSetterDummy');
        $this->assertEquals('bar', $obj->getFoo());
    }

    public function testHasGetterDenormalize()
    {
        $obj = $this->normalizer->denormalize(['foo' => true], ObjectWithHasGetterDummy::class);
        $this->assertTrue($obj->hasFoo());
    }

    public function testHasGetterNormalize()
    {
        $obj = new ObjectWithHasGetterDummy();
        $obj->setFoo(true);

        $this->assertEquals(
            ['foo' => true],
            $this->normalizer->normalize($obj, 'any')
        );
    }
}

class GetSetDummy
{
    protected $foo;
    private $bar;
    private $baz;
    protected $camelCase;
    protected $object;
    private static $staticObject;

    public function getFoo()
    {
        return $this->foo;
    }

    public function setFoo($foo)
    {
        $this->foo = $foo;
    }

    public function getBar()
    {
        return $this->bar;
    }

    public function setBar($bar)
    {
        $this->bar = $bar;
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

    public static function getStaticObject()
    {
        return self::$staticObject;
    }

    public static function setStaticObject($object)
    {
        self::$staticObject = $object;
    }

    protected function getPrivate()
    {
        throw new \RuntimeException('Dummy::getPrivate() should not be called');
    }
}

class GetConstructorDummy
{
    protected $foo;
    private $bar;
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

    public function getBar()
    {
        return $this->bar;
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

abstract class SerializerNormalizer implements SerializerInterface, NormalizerInterface
{
}

class GetConstructorOptionalArgsDummy
{
    protected $foo;
    private $bar;
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

    public function getBar()
    {
        return $this->bar;
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

class GetConstructorArgsWithDefaultValueDummy
{
    protected $foo;
    protected $bar;

    public function __construct($foo = [], $bar)
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

class ObjectConstructorArgsWithPrivateMutatorDummy
{
    private $foo;

    public function __construct($foo)
    {
        $this->setFoo($foo);
    }

    public function getFoo()
    {
        return $this->foo;
    }

    private function setFoo($foo)
    {
        $this->foo = $foo;
    }
}

class ObjectWithPrivateSetterDummy
{
    private $foo = 'bar';

    public function getFoo()
    {
        return $this->foo;
    }

    private function setFoo($foo)
    {
    }
}

class ObjectWithJustStaticSetterDummy
{
    private static $foo = 'bar';

    public static function getFoo()
    {
        return self::$foo;
    }

    public static function setFoo($foo)
    {
        self::$foo = $foo;
    }
}

class ObjectWithHasGetterDummy
{
    private $foo;

    public function setFoo($foo)
    {
        $this->foo = $foo;
    }

    public function hasFoo()
    {
        return $this->foo;
    }
}
