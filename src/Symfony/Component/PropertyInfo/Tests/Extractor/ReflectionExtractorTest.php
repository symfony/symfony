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
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyWriteInfo;
use Symfony\Component\PropertyInfo\Tests\Fixtures\AdderRemoverDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DefaultValue;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\NotInstantiable;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php71Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php71DummyExtended;
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
                'arrayWithKeys',
                'arrayWithKeysAndComplexValue',
                'arrayOfMixed',
                'listOfStrings',
                'parentAnnotation',
                'foo',
                'foo2',
                'foo3',
                'foo4',
                'foo5',
                'files',
                'propertyTypeStatic',
                'parentAnnotationNoParent',
                'rootDummyItems',
                'rootDummyItem',
                'a',
                'DOB',
                'Id',
                '123',
                'self',
                'realParent',
                'xTotals',
                'YT',
                'date',
                'element',
                'c',
                'ct',
                'cf',
                'd',
                'dt',
                'df',
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
                'arrayWithKeys',
                'arrayWithKeysAndComplexValue',
                'arrayOfMixed',
                'listOfStrings',
                'parentAnnotation',
                'foo',
                'foo2',
                'foo3',
                'foo4',
                'foo5',
                'files',
                'propertyTypeStatic',
                'parentAnnotationNoParent',
                'rootDummyItems',
                'rootDummyItem',
                'date',
                'c',
                'ct',
                'cf',
                'd',
                'dt',
                'df',
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
                'arrayWithKeys',
                'arrayWithKeysAndComplexValue',
                'arrayOfMixed',
                'listOfStrings',
                'parentAnnotation',
                'foo',
                'foo2',
                'foo3',
                'foo4',
                'foo5',
                'files',
                'propertyTypeStatic',
                'parentAnnotationNoParent',
                'rootDummyItems',
                'rootDummyItem',
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
            ['f', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable'))]],
            ['donotexist', null],
            ['staticGetter', null],
            ['staticSetter', null],
            ['self', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy')]],
            ['realParent', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')]],
            ['date', [new Type(Type::BUILTIN_TYPE_OBJECT, false, \DateTimeImmutable::class)]],
            ['dates', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_OBJECT, false, \DateTimeImmutable::class))]],
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
        yield ['true', [new Type(Type::BUILTIN_TYPE_TRUE)]];

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
            ['defaultArray', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true)]],
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

    public static function getReadableProperties()
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
            ['element', false],
        ];
    }

    /**
     * @dataProvider getWritableProperties
     */
    public function testIsWritable($property, $expected)
    {
        $this->assertSame(
            $expected,
            $this->extractor->isWritable(Dummy::class, $property, [])
        );
    }

    public static function getWritableProperties()
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

    public static function getInitializableProperties(): array
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

    public function testNullOnPrivateProtectedAccessor()
    {
        $barAcessor = $this->extractor->getReadInfo(Dummy::class, 'bar');
        $barMutator = $this->extractor->getWriteInfo(Dummy::class, 'bar');
        $bazAcessor = $this->extractor->getReadInfo(Dummy::class, 'baz');
        $bazMutator = $this->extractor->getWriteInfo(Dummy::class, 'baz');

        $this->assertNull($barAcessor);
        $this->assertEquals(PropertyWriteInfo::TYPE_NONE, $barMutator->getType());
        $this->assertNull($bazAcessor);
        $this->assertEquals(PropertyWriteInfo::TYPE_NONE, $bazMutator->getType());
    }

    public function testTypedProperties()
    {
        $this->assertEquals([new Type(Type::BUILTIN_TYPE_OBJECT, false, Dummy::class)], $this->extractor->getTypes(Php74Dummy::class, 'dummy'));
        $this->assertEquals([new Type(Type::BUILTIN_TYPE_BOOL, true)], $this->extractor->getTypes(Php74Dummy::class, 'nullableBoolProp'));
        $this->assertEquals([new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING))], $this->extractor->getTypes(Php74Dummy::class, 'stringCollection'));
        $this->assertEquals([new Type(Type::BUILTIN_TYPE_INT, true)], $this->extractor->getTypes(Php74Dummy::class, 'nullableWithDefault'));
        $this->assertEquals([new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true)], $this->extractor->getTypes(Php74Dummy::class, 'collection'));
    }

    /**
     * @dataProvider readAccessorProvider
     */
    public function testGetReadAccessor($class, $property, $found, $type, $name, $visibility, $static)
    {
        $extractor = new ReflectionExtractor(null, null, null, true, ReflectionExtractor::ALLOW_PUBLIC | ReflectionExtractor::ALLOW_PROTECTED | ReflectionExtractor::ALLOW_PRIVATE);
        $readAcessor = $extractor->getReadInfo($class, $property);

        if (!$found) {
            $this->assertNull($readAcessor);

            return;
        }

        $this->assertNotNull($readAcessor);
        $this->assertSame($type, $readAcessor->getType());
        $this->assertSame($name, $readAcessor->getName());
        $this->assertSame($visibility, $readAcessor->getVisibility());
        $this->assertSame($static, $readAcessor->isStatic());
    }

    public static function readAccessorProvider(): array
    {
        return [
            [Dummy::class, 'bar', true, PropertyReadInfo::TYPE_PROPERTY, 'bar', PropertyReadInfo::VISIBILITY_PRIVATE, false],
            [Dummy::class, 'baz', true, PropertyReadInfo::TYPE_PROPERTY, 'baz', PropertyReadInfo::VISIBILITY_PROTECTED, false],
            [Dummy::class, 'bal', true, PropertyReadInfo::TYPE_PROPERTY, 'bal', PropertyReadInfo::VISIBILITY_PUBLIC, false],
            [Dummy::class, 'parent', true, PropertyReadInfo::TYPE_PROPERTY, 'parent', PropertyReadInfo::VISIBILITY_PUBLIC, false],
            [Dummy::class, 'static', true, PropertyReadInfo::TYPE_METHOD, 'getStatic', PropertyReadInfo::VISIBILITY_PUBLIC, true],
            [Dummy::class, 'foo', true, PropertyReadInfo::TYPE_PROPERTY, 'foo', PropertyReadInfo::VISIBILITY_PUBLIC, false],
            [Php71Dummy::class, 'foo', true, PropertyReadInfo::TYPE_METHOD, 'getFoo', PropertyReadInfo::VISIBILITY_PUBLIC, false],
            [Php71Dummy::class, 'buz', true, PropertyReadInfo::TYPE_METHOD, 'getBuz', PropertyReadInfo::VISIBILITY_PUBLIC, false],
        ];
    }

    /**
     * @dataProvider writeMutatorProvider
     */
    public function testGetWriteMutator($class, $property, $allowConstruct, $found, $type, $name, $addName, $removeName, $visibility, $static)
    {
        $extractor = new ReflectionExtractor(null, null, null, true, ReflectionExtractor::ALLOW_PUBLIC | ReflectionExtractor::ALLOW_PROTECTED | ReflectionExtractor::ALLOW_PRIVATE);
        $writeMutator = $extractor->getWriteInfo($class, $property, [
            'enable_constructor_extraction' => $allowConstruct,
            'enable_getter_setter_extraction' => true,
        ]);

        if (!$found) {
            $this->assertEquals(PropertyWriteInfo::TYPE_NONE, $writeMutator->getType());

            return;
        }

        $this->assertNotNull($writeMutator);
        $this->assertSame($type, $writeMutator->getType());

        if (PropertyWriteInfo::TYPE_ADDER_AND_REMOVER === $writeMutator->getType()) {
            $this->assertNotNull($writeMutator->getAdderInfo());
            $this->assertSame($addName, $writeMutator->getAdderInfo()->getName());
            $this->assertNotNull($writeMutator->getRemoverInfo());
            $this->assertSame($removeName, $writeMutator->getRemoverInfo()->getName());
        }

        if (PropertyWriteInfo::TYPE_CONSTRUCTOR === $writeMutator->getType()) {
            $this->assertSame($name, $writeMutator->getName());
        }

        if (PropertyWriteInfo::TYPE_PROPERTY === $writeMutator->getType()) {
            $this->assertSame($name, $writeMutator->getName());
            $this->assertSame($visibility, $writeMutator->getVisibility());
            $this->assertSame($static, $writeMutator->isStatic());
        }

        if (PropertyWriteInfo::TYPE_METHOD === $writeMutator->getType()) {
            $this->assertSame($name, $writeMutator->getName());
            $this->assertSame($visibility, $writeMutator->getVisibility());
            $this->assertSame($static, $writeMutator->isStatic());
        }
    }

    public static function writeMutatorProvider(): array
    {
        return [
            [Dummy::class, 'bar', false, true, PropertyWriteInfo::TYPE_PROPERTY, 'bar', null, null, PropertyWriteInfo::VISIBILITY_PRIVATE, false],
            [Dummy::class, 'baz', false, true, PropertyWriteInfo::TYPE_PROPERTY, 'baz', null, null, PropertyWriteInfo::VISIBILITY_PROTECTED, false],
            [Dummy::class, 'bal', false, true, PropertyWriteInfo::TYPE_PROPERTY, 'bal', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Dummy::class, 'parent', false, true, PropertyWriteInfo::TYPE_PROPERTY, 'parent', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Dummy::class, 'staticSetter', false, true, PropertyWriteInfo::TYPE_METHOD, 'staticSetter', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, true],
            [Dummy::class, 'foo', false, true, PropertyWriteInfo::TYPE_PROPERTY, 'foo', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71Dummy::class, 'bar', false, true, PropertyWriteInfo::TYPE_METHOD, 'setBar', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71Dummy::class, 'string', false, false, '', '', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71Dummy::class, 'string', true, true,  PropertyWriteInfo::TYPE_CONSTRUCTOR, 'string', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71Dummy::class, 'baz', false, true, PropertyWriteInfo::TYPE_ADDER_AND_REMOVER, null, 'addBaz', 'removeBaz', PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71DummyExtended::class, 'bar', false, true, PropertyWriteInfo::TYPE_METHOD, 'setBar', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71DummyExtended::class, 'string', false, false, -1, '', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71DummyExtended::class, 'string', true, true, PropertyWriteInfo::TYPE_CONSTRUCTOR, 'string', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71DummyExtended::class, 'baz', false, true, PropertyWriteInfo::TYPE_ADDER_AND_REMOVER, null, 'addBaz', 'removeBaz', PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71DummyExtended2::class, 'bar', false, true, PropertyWriteInfo::TYPE_METHOD, 'setBar', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71DummyExtended2::class, 'string', false, false, '', '', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71DummyExtended2::class, 'string', true, false,  '', '', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71DummyExtended2::class, 'baz', false, true, PropertyWriteInfo::TYPE_ADDER_AND_REMOVER, null, 'addBaz', 'removeBaz', PropertyWriteInfo::VISIBILITY_PUBLIC, false],
        ];
    }

    public function testGetWriteInfoReadonlyProperties()
    {
        $writeMutatorConstructor = $this->extractor->getWriteInfo(Php81Dummy::class, 'foo', ['enable_constructor_extraction' => true]);
        $writeMutatorWithoutConstructor = $this->extractor->getWriteInfo(Php81Dummy::class, 'foo', ['enable_constructor_extraction' => false]);

        $this->assertSame(PropertyWriteInfo::TYPE_CONSTRUCTOR, $writeMutatorConstructor->getType());
        $this->assertSame(PropertyWriteInfo::TYPE_NONE, $writeMutatorWithoutConstructor->getType());
    }

    /**
     * @dataProvider extractConstructorTypesProvider
     */
    public function testExtractConstructorTypes(string $property, array $type = null)
    {
        $this->assertEquals($type, $this->extractor->getTypesFromConstructor('Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummy', $property));
    }

    public static function extractConstructorTypesProvider(): array
    {
        return [
            ['timezone', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeZone')]],
            ['date', null],
            ['dateObject', null],
            ['dateTime', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable')]],
            ['ddd', null],
        ];
    }
}
