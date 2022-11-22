<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Extractor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\AdderRemoverDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DefaultValue;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\NotInstantiable;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php71Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php71DummyExtended2;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php74Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php7Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php7ParentDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php81Dummy;
use Symfony\Component\PropertyInfo\Type;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ReflectionExtractorTest extends TestCase
{
    /**
     * @var ReflectionExtractor
     */
    private $extractor;

    protected function setUp(): void
    {
        $this->extractor = new ReflectionExtractor();
    }

    public function testGetProperties()
    {
        $this->assertSame(
            [
                'bal',
                'parent',
                'collection',
                'nestedCollection',
                'mixedCollection',
                'B',
                'Guid',
                'g',
                'h',
                'i',
                'j',
                'nullableCollectionOfNonNullableElements',
                'emptyVar',
                'iteratorCollection',
                'iteratorCollectionWithKey',
                'nestedIterators',
                'parentAnnotation',
                'foo',
                'foo2',
                'foo3',
                'foo4',
                'foo5',
                'files',
                'propertyTypeStatic',
                'parentAnnotationNoParent',
                'a',
                'DOB',
                'Id',
                '123',
                'self',
                'realParent',
                'xTotals',
                'YT',
                'date',
                'c',
                'd',
                'e',
                'f',
            ],
            $this->extractor->getProperties('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy')
        );

        $this->assertNull($this->extractor->getProperties('Symfony\Component\PropertyInfo\Tests\Fixtures\NoProperties'));
    }

    public function testGetPropertiesWithCustomPrefixes()
    {
        $customExtractor = new ReflectionExtractor(['add', 'remove'], ['is', 'can']);

        $this->assertSame(
            [
                'bal',
                'parent',
                'collection',
                'nestedCollection',
                'mixedCollection',
                'B',
                'Guid',
                'g',
                'h',
                'i',
                'j',
                'nullableCollectionOfNonNullableElements',
                'emptyVar',
                'iteratorCollection',
                'iteratorCollectionWithKey',
                'nestedIterators',
                'parentAnnotation',
                'foo',
                'foo2',
                'foo3',
                'foo4',
                'foo5',
                'files',
                'propertyTypeStatic',
                'parentAnnotationNoParent',
                'date',
                'c',
                'd',
                'e',
                'f',
            ],
            $customExtractor->getProperties('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy')
        );
    }

    public function testGetPropertiesWithNoPrefixes()
    {
        $noPrefixExtractor = new ReflectionExtractor([], [], []);

        $this->assertSame(
            [
                'bal',
                'parent',
                'collection',
                'nestedCollection',
                'mixedCollection',
                'B',
                'Guid',
                'g',
                'h',
                'i',
                'j',
                'nullableCollectionOfNonNullableElements',
                'emptyVar',
                'iteratorCollection',
                'iteratorCollectionWithKey',
                'nestedIterators',
                'parentAnnotation',
                'foo',
                'foo2',
                'foo3',
                'foo4',
                'foo5',
                'files',
                'propertyTypeStatic',
                'parentAnnotationNoParent',
            ],
            $noPrefixExtractor->getProperties('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy')
        );
    }

    /**
     * @dataProvider typesProvider
     */
    public function testExtractors($property, array $type = null)
    {
        $this->assertEquals($type, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', $property, []));
    }

    public static function typesProvider()
    {
        return [
            ['a', null],
            ['b', [new Type(Type::BUILTIN_TYPE_OBJECT, true, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')]],
            ['c', [new Type(Type::BUILTIN_TYPE_BOOL)]],
            ['d', [new Type(Type::BUILTIN_TYPE_BOOL)]],
            ['e', null],
            ['f', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTime'))]],
            ['donotexist', null],
            ['staticGetter', null],
            ['staticSetter', null],
            ['self', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy')]],
            ['realParent', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')]],
            ['date', [new Type(Type::BUILTIN_TYPE_OBJECT, false, \DateTime::class)]],
            ['dates', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_OBJECT, false, \DateTime::class))]],
        ];
    }

    /**
     * @dataProvider php7TypesProvider
     */
    public function testExtractPhp7Type(string $class, string $property, array $type = null)
    {
        $this->assertEquals($type, $this->extractor->getTypes($class, $property, []));
    }

    public static function php7TypesProvider()
    {
        return [
            [Php7Dummy::class, 'foo', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true)]],
            [Php7Dummy::class, 'bar', [new Type(Type::BUILTIN_TYPE_INT)]],
            [Php7Dummy::class, 'baz', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING))]],
            [Php7Dummy::class, 'buz', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\Php7Dummy')]],
            [Php7Dummy::class, 'biz', [new Type(Type::BUILTIN_TYPE_OBJECT, false, Php7ParentDummy::class)]],
            [Php7Dummy::class, 'donotexist', null],
            [Php7ParentDummy::class, 'parent', [new Type(Type::BUILTIN_TYPE_OBJECT, false, \stdClass::class)]],
        ];
    }

    /**
     * @dataProvider php71TypesProvider
     */
    public function testExtractPhp71Type($property, array $type = null)
    {
        $this->assertEquals($type, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\Php71Dummy', $property, []));
    }

    public static function php71TypesProvider()
    {
        return [
            ['foo', [new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true)]],
            ['buz', [new Type(Type::BUILTIN_TYPE_NULL)]],
            ['bar', [new Type(Type::BUILTIN_TYPE_INT, true)]],
            ['baz', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING))]],
            ['donotexist', null],
        ];
    }

    /**
     * @dataProvider php80TypesProvider
     *
     * @requires PHP 8
     */
    public function testExtractPhp80Type($property, array $type = null)
    {
        $this->assertEquals($type, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\Php80Dummy', $property, []));
    }

    public static function php80TypesProvider()
    {
        return [
            ['foo', [new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true)]],
            ['bar', [new Type(Type::BUILTIN_TYPE_INT, true)]],
            ['timeout', [new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_FLOAT)]],
            ['optional', [new Type(Type::BUILTIN_TYPE_INT, true), new Type(Type::BUILTIN_TYPE_FLOAT, true)]],
            ['string', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Stringable'), new Type(Type::BUILTIN_TYPE_STRING)]],
            ['payload', null],
            ['data', null],
            ['mixedProperty', null],
        ];
    }

    /**
     * @dataProvider php81TypesProvider
     *
     * @requires PHP 8.1
     */
    public function testExtractPhp81Type($property, array $type = null)
    {
        $this->assertEquals($type, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\Php81Dummy', $property, []));
    }

    public static function php81TypesProvider()
    {
        return [
            ['nothing', null],
            ['collection', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Traversable'), new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Countable')]],
        ];
    }

    /**
     * @requires PHP 8.1
     */
    public function testReadonlyPropertiesAreNotWriteable()
    {
        $this->assertFalse($this->extractor->isWritable(Php81Dummy::class, 'foo'));
    }

    /**
     * @dataProvider php82TypesProvider
     *
     * @requires PHP 8.2
     */
    public function testExtractPhp82Type($property, array $type = null)
    {
        $this->assertEquals($type, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\Php82Dummy', $property, []));
    }

    public static function php82TypesProvider(): iterable
    {
        yield ['nil', null];
        yield ['false', [new Type(Type::BUILTIN_TYPE_FALSE)]];

        // Nesting intersection and union types is not supported yet,
        // but we should make sure this kind of composite types does not crash the extractor.
        yield ['someCollection', null];
    }

    /**
     * @dataProvider defaultValueProvider
     */
    public function testExtractWithDefaultValue($property, $type)
    {
        $this->assertEquals($type, $this->extractor->getTypes(DefaultValue::class, $property, []));
    }

    public static function defaultValueProvider()
    {
        return [
            ['defaultInt', [new Type(Type::BUILTIN_TYPE_INT, false)]],
            ['defaultFloat', [new Type(Type::BUILTIN_TYPE_FLOAT, false)]],
            ['defaultString', [new Type(Type::BUILTIN_TYPE_STRING, false)]],
            ['defaultArray', [new Type(Type::BUILTIN_TYPE_ARRAY, false)]],
            ['defaultNull', null],
        ];
    }

    /**
     * @dataProvider getReadableProperties
     */
    public function testIsReadable($property, $expected)
    {
        $this->assertSame(
            $expected,
            $this->extractor->isReadable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', $property, [])
        );
    }

    public function getReadableProperties()
    {
        return [
            ['bar', false],
            ['baz', false],
            ['parent', true],
            ['a', true],
            ['b', false],
            ['c', true],
            ['d', true],
            ['e', false],
            ['f', false],
            ['Id', true],
            ['id', true],
            ['Guid', true],
            ['guid', false],
        ];
    }

    /**
     * @dataProvider getWritableProperties
     */
    public function testIsWritable($property, $expected)
    {
        $this->assertSame(
            $expected,
            $this->extractor->isWritable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', $property, [])
        );
    }

    public function getWritableProperties()
    {
        return [
            ['bar', false],
            ['baz', false],
            ['parent', true],
            ['a', false],
            ['b', true],
            ['c', false],
            ['d', false],
            ['e', true],
            ['f', true],
            ['Id', false],
            ['Guid', true],
            ['guid', false],
        ];
    }

    public function testSingularize()
    {
        $this->assertTrue($this->extractor->isWritable(AdderRemoverDummy::class, 'analyses'));
        $this->assertTrue($this->extractor->isWritable(AdderRemoverDummy::class, 'feet'));
        $this->assertEquals(['analyses', 'feet'], $this->extractor->getProperties(AdderRemoverDummy::class));
    }

    public function testPrivatePropertyExtractor()
    {
        $privateExtractor = new ReflectionExtractor(null, null, null, true, ReflectionExtractor::ALLOW_PUBLIC | ReflectionExtractor::ALLOW_PRIVATE | ReflectionExtractor::ALLOW_PROTECTED);
        $properties = $privateExtractor->getProperties(Dummy::class);

        $this->assertContains('bar', $properties);
        $this->assertContains('baz', $properties);

        $this->assertTrue($privateExtractor->isReadable(Dummy::class, 'bar'));
        $this->assertTrue($privateExtractor->isReadable(Dummy::class, 'baz'));

        $protectedExtractor = new ReflectionExtractor(null, null, null, true, ReflectionExtractor::ALLOW_PUBLIC | ReflectionExtractor::ALLOW_PROTECTED);
        $properties = $protectedExtractor->getProperties(Dummy::class);

        $this->assertNotContains('bar', $properties);
        $this->assertContains('baz', $properties);

        $this->assertFalse($protectedExtractor->isReadable(Dummy::class, 'bar'));
        $this->assertTrue($protectedExtractor->isReadable(Dummy::class, 'baz'));
    }

    /**
     * @dataProvider getInitializableProperties
     */
    public function testIsInitializable(string $class, string $property, bool $expected)
    {
        $this->assertSame($expected, $this->extractor->isInitializable($class, $property));
    }

    public function getInitializableProperties(): array
    {
        return [
            [Php71Dummy::class, 'string', true],
            [Php71Dummy::class, 'intPrivate', true],
            [Php71Dummy::class, 'notExist', false],
            [Php71DummyExtended2::class, 'intWithAccessor', true],
            [Php71DummyExtended2::class, 'intPrivate', false],
            [NotInstantiable::class, 'foo', false],
        ];
    }

    /**
     * @dataProvider constructorTypesProvider
     */
    public function testExtractTypeConstructor(string $class, string $property, array $type = null)
    {
        /* Check that constructor extractions works by default, and if passed in via context.
           Check that null is returned if constructor extraction is disabled */
        $this->assertEquals($type, $this->extractor->getTypes($class, $property, []));
        $this->assertEquals($type, $this->extractor->getTypes($class, $property, ['enable_constructor_extraction' => true]));
        $this->assertNull($this->extractor->getTypes($class, $property, ['enable_constructor_extraction' => false]));
    }

    public static function constructorTypesProvider(): array
    {
        return [
            // php71 dummy has following constructor: __construct(string $string, int $intPrivate)
            [Php71Dummy::class, 'string', [new Type(Type::BUILTIN_TYPE_STRING, false)]],
            [Php71Dummy::class, 'intPrivate', [new Type(Type::BUILTIN_TYPE_INT, false)]],
            // Php71DummyExtended2 adds int $intWithAccessor
            [Php71DummyExtended2::class, 'intWithAccessor', [new Type(Type::BUILTIN_TYPE_INT, false)]],
            [Php71DummyExtended2::class, 'intPrivate', [new Type(Type::BUILTIN_TYPE_INT, false)]],
            [DefaultValue::class, 'foo', null],
        ];
    }

    /**
     * @requires PHP 7.4
     */
    public function testTypedProperties()
    {
        $this->assertEquals([new Type(Type::BUILTIN_TYPE_OBJECT, false, Dummy::class)], $this->extractor->getTypes(Php74Dummy::class, 'dummy'));
        $this->assertEquals([new Type(Type::BUILTIN_TYPE_BOOL, true)], $this->extractor->getTypes(Php74Dummy::class, 'nullableBoolProp'));
        $this->assertEquals([new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING))], $this->extractor->getTypes(Php74Dummy::class, 'stringCollection'));
        $this->assertEquals([new Type(Type::BUILTIN_TYPE_INT, true)], $this->extractor->getTypes(Php74Dummy::class, 'nullableWithDefault'));
    }
}
