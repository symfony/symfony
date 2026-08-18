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

use phpDocumentor\Reflection\PseudoTypes\IntMask;
use phpDocumentor\Reflection\PseudoTypes\IntMaskOf;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyCollection;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\ChildOfParentUsingTrait;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\ChildOfParentWithPromotedSelfDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\ChildWithConstructorOverride;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\ChildWithoutConstructorOverride;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\ChildWithSelfDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\ClassUsingNestedTrait;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\ClassUsingTraitWithSelfDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\ParentUsingTraitWithSelfDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\ParentWithPromotedPropertyDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\ParentWithPromotedSelfDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\ParentWithSelfDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\MultiParameterAdderDocDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php80Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\PseudoTypeDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\RejectedCandidateDocDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsedInTrait;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsingTrait;
use Symfony\Component\PropertyInfo\Tests\Fixtures\VoidNeverReturnTypeDummy;
use Symfony\Component\PropertyInfo\Type;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PhpDocExtractorTest extends TestCase
{
    private PhpDocExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new PhpDocExtractor();
    }

    public function testAdderWithSeveralRequiredParametersIsIgnored()
    {
        $this->assertNull($this->extractor->getTypes(MultiParameterAdderDocDummy::class, 'link'));
    }

    public function testRejectedCandidateMethodsAreIgnored()
    {
        $this->assertNull($this->extractor->getTypes(RejectedCandidateDocDummy::class, 'foo'));
        $this->assertNull($this->extractor->getTypes(RejectedCandidateDocDummy::class, 'bar'));
    }

    /**
     * @dataProvider typesProvider
     */
    public function testExtract($property, ?array $type, $shortDescription, $longDescription)
    {
        $this->assertEquals($type, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', $property));
        $this->assertSame($shortDescription, $this->extractor->getShortDescription('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', $property));
        $this->assertSame($longDescription, $this->extractor->getLongDescription('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', $property));
    }

    public function testParamTagTypeIsOmitted()
    {
        $this->assertNull($this->extractor->getTypes(OmittedParamTagTypeDocBlock::class, 'omittedType'));
    }

    public static function invalidTypesProvider()
    {
        return [
            'pub' => ['pub', null, null],
            'stat' => ['stat', null, null],
            'bar' => ['bar', 'Bar.', null],
        ];
    }

    /**
     * @dataProvider invalidTypesProvider
     */
    public function testInvalid($property, $shortDescription, $longDescription)
    {
        $this->assertNull($this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy', $property));
        $this->assertSame($shortDescription, $this->extractor->getShortDescription('Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy', $property));
        $this->assertSame($longDescription, $this->extractor->getLongDescription('Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy', $property));
    }

    /**
     * @group legacy
     */
    public function testEmptyParamAnnotation()
    {
        $this->assertNull($this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy', 'foo'));
        $this->assertSame('Foo.', $this->extractor->getShortDescription('Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy', 'foo'));
        $this->assertNull($this->extractor->getLongDescription('Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy', 'foo'));
    }

    /**
     * @dataProvider typesWithNoPrefixesProvider
     */
    public function testExtractTypesWithNoPrefixes($property, ?array $type = null)
    {
        $noPrefixExtractor = new PhpDocExtractor(null, [], [], []);

        $this->assertEquals($type, $noPrefixExtractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', $property));
    }

    public static function typesProvider()
    {
        return [
            ['foo', null, 'Short description.', 'Long description.'],
            ['bar', [new Type(Type::BUILTIN_TYPE_STRING)], 'This is bar', null],
            ['baz', [new Type(Type::BUILTIN_TYPE_INT)], 'Should be used.', null],
            ['foo2', [new Type(Type::BUILTIN_TYPE_FLOAT)], null, null],
            ['foo3', [new Type(Type::BUILTIN_TYPE_CALLABLE)], null, null],
            ['foo4', [new Type(Type::BUILTIN_TYPE_NULL)], null, null],
            ['foo5', null, null, null],
            [
                'files',
                [
                    new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, null, new Type(Type::BUILTIN_TYPE_OBJECT, false, 'SplFileInfo')),
                    new Type(Type::BUILTIN_TYPE_RESOURCE),
                ],
                null,
                null,
            ],
            ['bal', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable')], null, null],
            ['parent', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')], null, null],
            ['collection', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, null, new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable'))], null, null],
            ['nestedCollection', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, null, new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, null, new Type(Type::BUILTIN_TYPE_STRING, false)))], null, null],
            ['mixedCollection', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, null, null)], null, null],
            ['a', [new Type(Type::BUILTIN_TYPE_INT)], 'A.', null],
            ['b', [new Type(Type::BUILTIN_TYPE_OBJECT, true, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')], 'B.', null],
            ['c', [new Type(Type::BUILTIN_TYPE_BOOL, true)], null, null],
            ['ct', [new Type(Type::BUILTIN_TYPE_TRUE, true)], null, null],
            ['cf', [new Type(Type::BUILTIN_TYPE_FALSE, true)], null, null],
            ['d', [new Type(Type::BUILTIN_TYPE_BOOL)], null, null],
            ['dt', [new Type(Type::BUILTIN_TYPE_TRUE)], null, null],
            ['df', [new Type(Type::BUILTIN_TYPE_FALSE)], null, null],
            ['e', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_RESOURCE))], null, null],
            ['f', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable'))], null, null],
            ['g', [new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true)], 'Nullable array.', null],
            ['h', [new Type(Type::BUILTIN_TYPE_STRING, true)], null, null],
            ['i', [new Type(Type::BUILTIN_TYPE_STRING, true), new Type(Type::BUILTIN_TYPE_INT, true)], null, null],
            ['j', [new Type(Type::BUILTIN_TYPE_OBJECT, true, 'DateTimeImmutable')], null, null],
            ['nullableCollectionOfNonNullableElements', [new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true, null, new Type(Type::BUILTIN_TYPE_INT, false))], null, null],
            ['donotexist', null, null, null],
            ['staticGetter', null, null, null],
            ['staticSetter', null, null, null],
            ['emptyVar', null, 'This should not be removed.', null],
            ['arrayWithKeys', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_STRING), new Type(Type::BUILTIN_TYPE_STRING))], null, null],
            ['arrayOfMixed', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_STRING), null)], null, null],
            ['listOfStrings', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING))], null, null],
            ['self', [new Type(Type::BUILTIN_TYPE_OBJECT, false, Dummy::class)], null, null],
            ['collectionAsObject', [new Type(Type::BUILTIN_TYPE_OBJECT, false, DummyCollection::class, true, [new Type(Type::BUILTIN_TYPE_INT)], [new Type(Type::BUILTIN_TYPE_STRING)])], null, null],
            ['unionWithMixed', null, null, null],
        ];
    }

    /**
     * @dataProvider provideCollectionTypes
     */
    public function testExtractCollection($property, ?array $type, $shortDescription, $longDescription)
    {
        $this->testExtract($property, $type, $shortDescription, $longDescription);
    }

    public static function provideCollectionTypes()
    {
        return [
            ['iteratorCollection', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Iterator', true, [new Type(Type::BUILTIN_TYPE_STRING), new Type(Type::BUILTIN_TYPE_INT)], new Type(Type::BUILTIN_TYPE_STRING))], null, null],
            ['iteratorCollectionWithKey', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Iterator', true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING))], null, null],
            [
                'nestedIterators',
                [new Type(
                    Type::BUILTIN_TYPE_OBJECT,
                    false,
                    'Iterator',
                    true,
                    new Type(Type::BUILTIN_TYPE_INT),
                    new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Iterator', true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING))
                )],
                null,
                null,
            ],
            [
                'arrayWithKeys',
                [new Type(
                    Type::BUILTIN_TYPE_ARRAY,
                    false,
                    null,
                    true,
                    new Type(Type::BUILTIN_TYPE_STRING),
                    new Type(Type::BUILTIN_TYPE_STRING)
                )],
                null,
                null,
            ],
            [
                'arrayWithKeysAndComplexValue',
                [new Type(
                    Type::BUILTIN_TYPE_ARRAY,
                    false,
                    null,
                    true,
                    new Type(Type::BUILTIN_TYPE_STRING),
                    new Type(
                        Type::BUILTIN_TYPE_ARRAY,
                        true,
                        null,
                        true,
                        new Type(Type::BUILTIN_TYPE_INT),
                        new Type(Type::BUILTIN_TYPE_STRING, true)
                    )
                )],
                null,
                null,
            ],
        ];
    }

    /**
     * @dataProvider typesWithCustomPrefixesProvider
     */
    public function testExtractTypesWithCustomPrefixes($property, ?array $type = null)
    {
        $customExtractor = new PhpDocExtractor(null, ['add', 'remove'], ['is', 'can']);

        $this->assertEquals($type, $customExtractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', $property));
    }

    public static function typesWithCustomPrefixesProvider()
    {
        return [
            ['foo', null, 'Short description.', 'Long description.'],
            ['bar', [new Type(Type::BUILTIN_TYPE_STRING)], 'This is bar', null],
            ['baz', [new Type(Type::BUILTIN_TYPE_INT)], 'Should be used.', null],
            ['foo2', [new Type(Type::BUILTIN_TYPE_FLOAT)], null, null],
            ['foo3', [new Type(Type::BUILTIN_TYPE_CALLABLE)], null, null],
            ['foo4', [new Type(Type::BUILTIN_TYPE_NULL)], null, null],
            ['foo5', null, null, null],
            [
                'files',
                [
                    new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, null, new Type(Type::BUILTIN_TYPE_OBJECT, false, 'SplFileInfo')),
                    new Type(Type::BUILTIN_TYPE_RESOURCE),
                ],
                null,
                null,
            ],
            ['bal', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable')], null, null],
            ['parent', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')], null, null],
            ['collection', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, null, new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable'))], null, null],
            ['nestedCollection', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, null, new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, null, new Type(Type::BUILTIN_TYPE_STRING, false)))], null, null],
            ['mixedCollection', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, null, null)], null, null],
            ['a', null, 'A.', null],
            ['b', null, 'B.', null],
            ['c', [new Type(Type::BUILTIN_TYPE_BOOL, true)], null, null],
            ['d', [new Type(Type::BUILTIN_TYPE_BOOL)], null, null],
            ['e', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_RESOURCE))], null, null],
            ['f', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable'))], null, null],
            ['g', [new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true)], 'Nullable array.', null],
            ['h', [new Type(Type::BUILTIN_TYPE_STRING, true)], null, null],
            ['i', [new Type(Type::BUILTIN_TYPE_STRING, true), new Type(Type::BUILTIN_TYPE_INT, true)], null, null],
            ['j', [new Type(Type::BUILTIN_TYPE_OBJECT, true, 'DateTimeImmutable')], null, null],
            ['nullableCollectionOfNonNullableElements', [new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true, null, new Type(Type::BUILTIN_TYPE_INT, false))], null, null],
            ['nonNullableCollectionOfNullableElements', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, null, new Type(Type::BUILTIN_TYPE_INT, true))], null, null],
            ['nullableCollectionOfMultipleNonNullableElementTypes', [new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true, null, [new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING)])], null, null],
            ['donotexist', null, null, null],
            ['staticGetter', null, null, null],
            ['staticSetter', null, null, null],
        ];
    }

    public static function typesWithNoPrefixesProvider()
    {
        return [
            ['foo', null, 'Short description.', 'Long description.'],
            ['bar', [new Type(Type::BUILTIN_TYPE_STRING)], 'This is bar', null],
            ['baz', [new Type(Type::BUILTIN_TYPE_INT)], 'Should be used.', null],
            ['foo2', [new Type(Type::BUILTIN_TYPE_FLOAT)], null, null],
            ['foo3', [new Type(Type::BUILTIN_TYPE_CALLABLE)], null, null],
            ['foo4', [new Type(Type::BUILTIN_TYPE_NULL)], null, null],
            ['foo5', null, null, null],
            [
                'files',
                [
                    new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, null, new Type(Type::BUILTIN_TYPE_OBJECT, false, 'SplFileInfo')),
                    new Type(Type::BUILTIN_TYPE_RESOURCE),
                ],
                null,
                null,
            ],
            ['bal', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable')], null, null],
            ['parent', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')], null, null],
            ['collection', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, null, new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable'))], null, null],
            ['nestedCollection', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, null, new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, null, new Type(Type::BUILTIN_TYPE_STRING, false)))], null, null],
            ['mixedCollection', [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, null, null)], null, null],
            ['a', null, 'A.', null],
            ['b', null, 'B.', null],
            ['c', null, null, null],
            ['d', null, null, null],
            ['e', null, null, null],
            ['f', null, null, null],
            ['g', [new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true)], 'Nullable array.', null],
            ['h', [new Type(Type::BUILTIN_TYPE_STRING, true)], null, null],
            ['i', [new Type(Type::BUILTIN_TYPE_STRING, true), new Type(Type::BUILTIN_TYPE_INT, true)], null, null],
            ['j', [new Type(Type::BUILTIN_TYPE_OBJECT, true, 'DateTimeImmutable')], null, null],
            ['nullableCollectionOfNonNullableElements', [new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true, null, new Type(Type::BUILTIN_TYPE_INT, false))], null, null],
            ['donotexist', null, null, null],
            ['staticGetter', null, null, null],
            ['staticSetter', null, null, null],
        ];
    }

    public function testReturnNullOnEmptyDocBlock()
    {
        $this->assertNull($this->extractor->getShortDescription(EmptyDocBlock::class, 'foo'));
    }

    public static function dockBlockFallbackTypesProvider()
    {
        return [
            'pub' => [
                'pub', [new Type(Type::BUILTIN_TYPE_STRING)],
            ],
            'protAcc' => [
                'protAcc', [new Type(Type::BUILTIN_TYPE_INT)],
            ],
            'protMut' => [
                'protMut', [new Type(Type::BUILTIN_TYPE_BOOL)],
            ],
        ];
    }

    /**
     * @dataProvider dockBlockFallbackTypesProvider
     */
    public function testDocBlockFallback($property, $types)
    {
        $this->assertEquals($types, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\DockBlockFallback', $property));
    }

    /**
     * @dataProvider propertiesDefinedByTraitsProvider
     */
    public function testPropertiesDefinedByTraits(string $property, Type $type)
    {
        $this->assertEquals([$type], $this->extractor->getTypes(DummyUsingTrait::class, $property));
    }

    public static function propertiesDefinedByTraitsProvider(): array
    {
        return [
            ['propertyInTraitPrimitiveType', new Type(Type::BUILTIN_TYPE_STRING)],
            ['propertyInTraitObjectSameNamespace', new Type(Type::BUILTIN_TYPE_OBJECT, false, DummyUsedInTrait::class)],
            ['propertyInTraitObjectDifferentNamespace', new Type(Type::BUILTIN_TYPE_OBJECT, false, Dummy::class)],
            ['propertyInExternalTraitPrimitiveType', new Type(Type::BUILTIN_TYPE_STRING)],
            ['propertyInExternalTraitObjectSameNamespace', new Type(Type::BUILTIN_TYPE_OBJECT, false, Dummy::class)],
            ['propertyInExternalTraitObjectDifferentNamespace', new Type(Type::BUILTIN_TYPE_OBJECT, false, DummyUsedInTrait::class)],
        ];
    }

    /**
     * @dataProvider methodsDefinedByTraitsProvider
     */
    public function testMethodsDefinedByTraits(string $property, Type $type)
    {
        $this->assertEquals([$type], $this->extractor->getTypes(DummyUsingTrait::class, $property));
    }

    public static function methodsDefinedByTraitsProvider(): array
    {
        return [
            ['methodInTraitPrimitiveType', new Type(Type::BUILTIN_TYPE_STRING)],
            ['methodInTraitObjectSameNamespace', new Type(Type::BUILTIN_TYPE_OBJECT, false, DummyUsedInTrait::class)],
            ['methodInTraitObjectDifferentNamespace', new Type(Type::BUILTIN_TYPE_OBJECT, false, Dummy::class)],
            ['methodInExternalTraitPrimitiveType', new Type(Type::BUILTIN_TYPE_STRING)],
            ['methodInExternalTraitObjectSameNamespace', new Type(Type::BUILTIN_TYPE_OBJECT, false, Dummy::class)],
            ['methodInExternalTraitObjectDifferentNamespace', new Type(Type::BUILTIN_TYPE_OBJECT, false, DummyUsedInTrait::class)],
        ];
    }

    /**
     * @dataProvider propertiesStaticTypeProvider
     */
    public function testPropertiesStaticType(string $class, string $property, Type $type)
    {
        $this->assertEquals([$type], $this->extractor->getTypes($class, $property));
    }

    public static function propertiesStaticTypeProvider(): array
    {
        return [
            [ParentDummy::class, 'propertyTypeStatic', new Type(Type::BUILTIN_TYPE_OBJECT, false, ParentDummy::class)],
            [Dummy::class, 'propertyTypeStatic', new Type(Type::BUILTIN_TYPE_OBJECT, false, Dummy::class)],
        ];
    }

    /**
     * @dataProvider propertiesParentTypeProvider
     */
    public function testPropertiesParentType(string $class, string $property, ?array $types)
    {
        $this->assertEquals($types, $this->extractor->getTypes($class, $property));
    }

    public static function propertiesParentTypeProvider(): array
    {
        return [
            [ParentDummy::class, 'parentAnnotationNoParent', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'parent')]],
            [Dummy::class, 'parentAnnotation', [new Type(Type::BUILTIN_TYPE_OBJECT, false, ParentDummy::class)]],
        ];
    }

    public function testUnknownPseudoType()
    {
        $this->assertEquals([
            new Type(Type::BUILTIN_TYPE_OBJECT, false, 'Symfony\\Component\\PropertyInfo\\Tests\\Fixtures\\unknownpseudo'),
        ], $this->extractor->getTypes(PseudoTypeDummy::class, 'unknownPseudoType'));
    }

    public function testScalarPseudoType()
    {
        $this->assertEquals([
            new Type(Type::BUILTIN_TYPE_BOOL),
            new Type(Type::BUILTIN_TYPE_FLOAT),
            new Type(Type::BUILTIN_TYPE_INT),
            new Type(Type::BUILTIN_TYPE_STRING),
        ], $this->extractor->getTypes(PseudoTypeDummy::class, 'scalarPseudoType'));
    }

    public function testGenericInterface()
    {
        $this->assertNull($this->extractor->getTypes(Dummy::class, 'genericInterface'));
    }

    /**
     * @dataProvider constructorTypesProvider
     */
    public function testExtractConstructorTypes($property, ?array $type = null)
    {
        $this->assertEquals($type, $this->extractor->getTypesFromConstructor('Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummy', $property));
    }

    public static function constructorTypesProvider()
    {
        return [
            ['date', [new Type(Type::BUILTIN_TYPE_INT)]],
            ['timezone', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeZone')]],
            ['dateObject', [new Type(Type::BUILTIN_TYPE_OBJECT, false, 'DateTimeInterface')]],
            ['dateTime', null],
            ['ddd', null],
            ['mixed', null],
        ];
    }

    /**
     * @dataProvider pseudoTypesProvider
     */
    public function testPseudoTypes($property, ?array $type)
    {
        $this->assertEquals($type, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\PseudoTypesDummy', $property));
    }

    public static function pseudoTypesProvider(): array
    {
        return [
            ['classString', [new Type(Type::BUILTIN_TYPE_STRING, false, null)]],
            ['classStringGeneric', [new Type(Type::BUILTIN_TYPE_STRING, false, null)]],
            ['htmlEscapedString', [new Type(Type::BUILTIN_TYPE_STRING, false, null)]],
            ['lowercaseString', [new Type(Type::BUILTIN_TYPE_STRING, false, null)]],
            ['nonEmptyLowercaseString', [new Type(Type::BUILTIN_TYPE_STRING, false, null)]],
            ['nonEmptyString', [new Type(Type::BUILTIN_TYPE_STRING, false, null)]],
            ['numericString', [new Type(Type::BUILTIN_TYPE_STRING, false, null)]],
            ['traitString', [new Type(Type::BUILTIN_TYPE_STRING, false, null)]],
            ['positiveInt', [new Type(Type::BUILTIN_TYPE_INT, false, null)]],
            ['true', [new Type(Type::BUILTIN_TYPE_TRUE, false, null)]],
            ['false', [new Type(Type::BUILTIN_TYPE_FALSE, false, null)]],
            ['valueOfStrings', null],
            ['valueOfIntegers', null],
            ['keyOfStrings', null],
            ['keyOfIntegers', null],
            ['arrayKey', null],
            ['intMask', class_exists(IntMask::class) ? [new Type(Type::BUILTIN_TYPE_INT, false, null)] : null],
            ['intMaskOf', class_exists(IntMaskOf::class) ? [new Type(Type::BUILTIN_TYPE_INT, false, null)] : null],
            ['conditional', null],
            ['offsetAccess', null],
        ];
    }

    /**
     * @dataProvider promotedPropertyProvider
     */
    public function testExtractPromotedProperty(string $property, ?array $types)
    {
        $this->assertEquals($types, $this->extractor->getTypes(Php80Dummy::class, $property));
    }

    public static function promotedPropertyProvider(): array
    {
        return [
            ['promoted', null],
            ['promotedAndMutated', [new Type(Type::BUILTIN_TYPE_STRING)]],
        ];
    }

    public function testSkipVoidNeverReturnTypeAccessors()
    {
        // Methods that return void or never should be skipped, so no types should be extracted
        $this->assertNull($this->extractor->getTypes(VoidNeverReturnTypeDummy::class, 'voidProperty'));
        $this->assertNull($this->extractor->getTypes(VoidNeverReturnTypeDummy::class, 'neverProperty'));
        // Normal getter should still work
        $types = $this->extractor->getTypes(VoidNeverReturnTypeDummy::class, 'normalProperty');
        $this->assertNotNull($types);
        $this->assertCount(1, $types);
        $this->assertEquals(Type::BUILTIN_TYPE_STRING, $types[0]->getBuiltinType());
    }

    /**
     * @param class-string $class
     * @param class-string $expectedResolvedClass
     *
     * @dataProvider selfDocBlockResolutionProvider
     */
    public function testSelfDocBlockResolvesToDeclaringClass(string $class, string $property, string $expectedResolvedClass)
    {
        $this->assertEquals([new Type(Type::BUILTIN_TYPE_OBJECT, false, $expectedResolvedClass)], $this->extractor->getTypes($class, $property));
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: class-string}>
     */
    public static function selfDocBlockResolutionProvider(): iterable
    {
        yield 'parent property' => [ParentWithSelfDocBlock::class, 'selfProp', ParentWithSelfDocBlock::class];
        yield 'parent property from child' => [ChildWithSelfDocBlock::class, 'selfProp', ParentWithSelfDocBlock::class];
        yield 'parent accessor' => [ParentWithSelfDocBlock::class, 'selfAccessor', ParentWithSelfDocBlock::class];
        yield 'parent accessor from child' => [ChildWithSelfDocBlock::class, 'selfAccessor', ParentWithSelfDocBlock::class];
        yield 'parent mutator' => [ParentWithSelfDocBlock::class, 'selfMutator', ParentWithSelfDocBlock::class];
        yield 'parent mutator from child' => [ChildWithSelfDocBlock::class, 'selfMutator', ParentWithSelfDocBlock::class];
        yield 'trait property' => [ClassUsingTraitWithSelfDocBlock::class, 'selfTraitProp', ClassUsingTraitWithSelfDocBlock::class];
        yield 'trait accessor' => [ClassUsingTraitWithSelfDocBlock::class, 'selfTraitAccessor', ClassUsingTraitWithSelfDocBlock::class];
        yield 'trait mutator' => [ClassUsingTraitWithSelfDocBlock::class, 'selfTraitMutator', ClassUsingTraitWithSelfDocBlock::class];
        yield 'trait property from child' => [ChildOfParentUsingTrait::class, 'selfTraitProp', ParentUsingTraitWithSelfDocBlock::class];
        yield 'trait accessor from child' => [ChildOfParentUsingTrait::class, 'selfTraitAccessor', ParentUsingTraitWithSelfDocBlock::class];
        yield 'trait mutator from child' => [ChildOfParentUsingTrait::class, 'selfTraitMutator', ParentUsingTraitWithSelfDocBlock::class];
        yield 'nested trait property' => [ClassUsingNestedTrait::class, 'innerSelfProp', ClassUsingNestedTrait::class];
        yield 'promoted property' => [ParentWithPromotedSelfDocBlock::class, 'promotedSelfProp', ParentWithPromotedSelfDocBlock::class];
        yield 'promoted property from child' => [ChildOfParentWithPromotedSelfDocBlock::class, 'promotedSelfProp', ParentWithPromotedSelfDocBlock::class];
    }

    /**
     * @dataProvider inheritedPromotedPropertyWithConstructorOverrideProvider
     */
    public function testInheritedPromotedPropertyWithConstructorOverride(string $class, string $property, ?array $expectedTypes)
    {
        $this->assertEquals($expectedTypes, $this->extractor->getTypes($class, $property));
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: ?array}>
     */
    public static function inheritedPromotedPropertyWithConstructorOverrideProvider(): iterable
    {
        $expectedItemsType = [new Type(
            Type::BUILTIN_TYPE_ARRAY,
            false,
            null,
            true,
            new Type(Type::BUILTIN_TYPE_STRING),
            new Type(Type::BUILTIN_TYPE_INT)
        )];

        yield 'parent promoted property' => [ParentWithPromotedPropertyDocBlock::class, 'items', $expectedItemsType];
        yield 'child without constructor override' => [ChildWithoutConstructorOverride::class, 'items', $expectedItemsType];
        yield 'child with constructor override' => [ChildWithConstructorOverride::class, 'items', $expectedItemsType];
    }
}

class EmptyDocBlock
{
    public $foo;
}

class OmittedParamTagTypeDocBlock
{
    /**
     * The type is omitted here to ensure that the extractor doesn't choke on missing types.
     *
     * @param $omittedTagType
     */
    public function setOmittedType(array $omittedTagType)
    {
    }
}
