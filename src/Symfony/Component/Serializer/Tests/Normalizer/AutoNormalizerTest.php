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

require_once __DIR__.'/ObjectNormalizerTest.php';

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Builder\DefinitionExtractor;
use Symfony\Component\Serializer\Builder\NormalizerBuilder;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Tests\Builder\FixtureHelper;
use Symfony\Component\Serializer\Tests\Fixtures\DummyPrivatePropertyWithoutGetter;
use Symfony\Component\Serializer\Tests\Fixtures\Sibling;
use Symfony\Component\Serializer\Tests\Fixtures\SiblingHolder;
use Symfony\Component\Serializer\Tests\Normalizer\Features\ObjectDummy;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AutoNormalizerTest extends TestCase
{
    private static NormalizerBuilder $builder;
    private static DefinitionExtractor $definitionExtractor;
    private static string $outputDir;

    public static function setUpBeforeClass(): void
    {
        self::$definitionExtractor = FixtureHelper::getDefinitionExtractor();
        self::$outputDir = \dirname(__DIR__).'/_output/SerializerBuilderFixtureTest';
        self::$builder = new NormalizerBuilder();

        parent::setUpBeforeClass();
    }

    private function getSerializer(string ...$inputClasses): Serializer
    {
        $normalizers = [];
        foreach($inputClasses as $inputClass) {
            $def = self::$definitionExtractor->getDefinition($inputClass);
            $result = self::$builder->build($def, self::$outputDir);
            $result->loadClass();

            $normalizers[] = new $result->classNs();
        }

        return new Serializer($normalizers);
    }

    public function testNormalizeObjectWithPrivatePropertyWithoutGetter()
    {
        $serializer = $this->getSerializer(DummyPrivatePropertyWithoutGetter::class);
        $obj = new DummyPrivatePropertyWithoutGetter();
        $this->assertEquals(
            ['bar' => 'bar'],
            $serializer->normalize($obj, 'any')
        );
    }

    public function testDenormalize()
    {
        $serializer = $this->getSerializer(ObjectDummy::class);
        $obj = $serializer->denormalize(
            ['foo' => 'foo', 'bar' => 'bar', 'baz' => true, 'fooBar' => 'foobar'],
            ObjectDummy::class,
            'any'
        );
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
        $this->assertTrue($obj->isBaz());
    }

    public function testDenormalizeWithObject()
    {
        $serializer = $this->getSerializer(ObjectDummy::class);
        $data = new \stdClass();
        $data->foo = 'foo';
        $data->bar = 'bar';
        $data->fooBar = 'foobar';
        $obj = $serializer->denormalize($data, ObjectDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
    }

    public function testDenormalizeNull()
    {
        $serializer = $this->getSerializer(ObjectDummy::class);
        $this->assertEquals(new ObjectDummy(), $serializer->denormalize(null, ObjectDummy::class));
    }

    public function testConstructorDenormalize()
    {
        $serializer = $this->getSerializer(ObjectConstructorDummy::class);
        $obj = $serializer->denormalize(
            ['foo' => 'foo', 'bar' => 'bar', 'baz' => true, 'fooBar' => 'foobar'],
            ObjectConstructorDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->bar);
        $this->assertTrue($obj->isBaz());
    }

    public function testConstructorDenormalizeWithNullArgument()
    {
        $serializer = $this->getSerializer(ObjectConstructorDummy::class);
        $obj = $serializer->denormalize(
            ['foo' => 'foo', 'bar' => null, 'baz' => true],
            ObjectConstructorDummy::class, 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertNull($obj->bar);
        $this->assertTrue($obj->isBaz());
    }

    public function testConstructorDenormalizeWithMissingOptionalArgument()
    {
        $serializer = $this->getSerializer(ObjectConstructorOptionalArgsDummy::class);
        $obj = $serializer->denormalize(
            ['foo' => 'test', 'baz' => [1, 2, 3]],
            ObjectConstructorOptionalArgsDummy::class, 'any');
        $this->assertEquals('test', $obj->getFoo());
        $this->assertEquals([], $obj->bar);
        $this->assertEquals([1, 2, 3], $obj->getBaz());
    }

    public function testConstructorDenormalizeWithOptionalDefaultArgument()
    {
        $serializer = $this->getSerializer(ObjectConstructorArgsWithDefaultValueDummy::class);
        $obj = $serializer->denormalize(
            ['bar' => 'test'],
            ObjectConstructorArgsWithDefaultValueDummy::class, 'any');
        $this->assertEquals([], $obj->getFoo());
        $this->assertEquals('test', $obj->getBar());
    }

    public function testConstructorWithObjectDenormalize()
    {
        $serializer = $this->getSerializer(ObjectConstructorDummy::class);
        $data = new \stdClass();
        $data->foo = 'foo';
        $data->bar = 'bar';
        $data->baz = true;
        $data->fooBar = 'foobar';
        $obj = $serializer->denormalize($data, ObjectConstructorDummy::class, 'any');
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


        $serializer = $this->getSerializer(DummyWithConstructorObject::class, ObjectInner::class);
        $obj = $serializer->denormalize($data, DummyWithConstructorObject::class);
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

        $serializer = $this->getSerializer(DummyWithNullableConstructorObject::class);
        $obj = $serializer->denormalize($data, DummyWithNullableConstructorObject::class);
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

        $serializer = $this->getSerializer(DummyWithConstructorInexistingObject::class);
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Could not denormalize object of type "Symfony\Component\Serializer\Tests\Normalizer\Unknown", no supporting normalizer found.');

        $serializer->denormalize($data, DummyWithConstructorInexistingObject::class);
    }

    public function testSiblingReference()
    {
        $serializer = $this->getSerializer(SiblingHolder::class, Sibling::class);
        $siblingHolder = new SiblingHolder();

        $expected = [
            'sibling0' => ['coopTilleuls' => 'Les-Tilleuls.coop'],
            'sibling1' => ['coopTilleuls' => 'Les-Tilleuls.coop'],
            'sibling2' => ['coopTilleuls' => 'Les-Tilleuls.coop'],
        ];
        $this->assertEquals($expected, $serializer->normalize($siblingHolder));
    }

    public function testDenormalizeNonExistingAttribute()
    {
        $serializer = $this->getSerializer(ObjectDummy::class);
        $this->assertEquals(
            new ObjectDummy(),
            $serializer->denormalize(['non_existing' => true], ObjectDummy::class)
        );
    }

    public function testNormalizeUpperCaseAttributes()
    {
        $serializer = $this->getSerializer(ObjectWithUpperCaseAttributeNames::class);
        $this->assertEquals(['Foo' => 'Foo', 'Bar' => 'BarBar'], $serializer->normalize(new ObjectWithUpperCaseAttributeNames()));
    }

    public function testDefaultObjectClassResolver()
    {
        $serializer = $this->getSerializer(ObjectDummy::class);

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
            $serializer->normalize($obj, 'any')
        );
    }
}
