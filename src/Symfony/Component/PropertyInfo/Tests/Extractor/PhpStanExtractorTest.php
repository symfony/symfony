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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Clazz;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummyWithoutDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DefaultValue;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DockBlockFallback;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyCollection;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyGeneric;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyNamespace;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyPropertyAndGetterWithDifferentTypes;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyUnionType;
use Symfony\Component\PropertyInfo\Tests\Fixtures\IFace;
use Symfony\Component\PropertyInfo\Tests\Fixtures\IntRangeDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php80Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php80PromotedDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\PhpStanPseudoTypesDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\RootDummy\RootDummyItem;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\AnotherNamespace\DummyInAnotherNamespace;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsedInTrait;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsingTrait;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;

require_once __DIR__.'/../Fixtures/Extractor/DummyNamespace.php';

/**
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
class PhpStanExtractorTest extends TestCase
{
    use ExpectDeprecationTrait;

    private PhpStanExtractor $extractor;
    private PhpDocExtractor $phpDocExtractor;

    protected function setUp(): void
    {
        $this->extractor = new PhpStanExtractor();
        $this->phpDocExtractor = new PhpDocExtractor();
    }

    /**
     * @group legacy
     *
     * @dataProvider provideLegacyTypes
     */
    public function testExtractLegacy($property, ?array $type = null)
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $this->assertEquals($type, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', $property));
    }

    /**
     * @group legacy
     */
    public function testParamTagTypeIsOmittedLegacy()
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $this->assertNull($this->extractor->getTypes(PhpStanOmittedParamTagTypeDocBlock::class, 'omittedType'));
    }

    public static function provideLegacyInvalidTypes()
    {
        return [
            'pub' => ['pub'],
            'stat' => ['stat'],
            'foo' => ['foo'],
            'bar' => ['bar'],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider provideLegacyInvalidTypes
     */
    public function testInvalidLegacy($property)
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $this->assertNull($this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy', $property));
    }

    /**
     * @group legacy
     *
     * @dataProvider provideLegacyTypesWithNoPrefixes
     */
    public function testExtractTypesWithNoPrefixesLegacy($property, ?array $type = null)
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $noPrefixExtractor = new PhpStanExtractor([], [], []);

        $this->assertEquals($type, $noPrefixExtractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', $property));
    }

    public static function provideLegacyTypes()
    {
        return [
            ['foo', null],
            ['bar', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING)]],
            ['baz', [new LegacyType(LegacyType::BUILTIN_TYPE_INT)]],
            ['foo2', [new LegacyType(LegacyType::BUILTIN_TYPE_FLOAT)]],
            ['foo3', [new LegacyType(LegacyType::BUILTIN_TYPE_CALLABLE)]],
            ['foo4', [new LegacyType(LegacyType::BUILTIN_TYPE_NULL)]],
            ['foo5', null],
            [
                'files',
                [
                    new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'SplFileInfo')),
                    new LegacyType(LegacyType::BUILTIN_TYPE_RESOURCE),
                ],
            ],
            ['bal', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable')]],
            ['parent', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')]],
            ['collection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable'))]],
            ['nestedCollection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_STRING, false)))]],
            ['mixedCollection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, [new LegacyType(LegacyType::BUILTIN_TYPE_INT)], null)]],
            ['a', [new LegacyType(LegacyType::BUILTIN_TYPE_INT)]],
            ['b', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, true, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')]],
            ['c', [new LegacyType(LegacyType::BUILTIN_TYPE_BOOL, true)]],
            ['d', [new LegacyType(LegacyType::BUILTIN_TYPE_BOOL)]],
            ['e', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_RESOURCE))]],
            ['f', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable'))]],
            ['g', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, true, null, true)]],
            ['h', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, true)]],
            ['j', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, true, 'DateTimeImmutable')]],
            ['nullableCollectionOfNonNullableElements', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, true, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_INT, false))]],
            ['donotexist', null],
            ['staticGetter', null],
            ['staticSetter', null],
            ['emptyVar', null],
            ['arrayWithKeys', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_STRING), new LegacyType(LegacyType::BUILTIN_TYPE_STRING))]],
            ['arrayOfMixed', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_STRING), null)]],
            ['listOfStrings', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_STRING))]],
            ['self', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Dummy::class)]],
            ['rootDummyItems', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, RootDummyItem::class))]],
            ['rootDummyItem', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, RootDummyItem::class)]],
            ['collectionAsObject', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, DummyCollection::class, true, [new LegacyType(LegacyType::BUILTIN_TYPE_INT)], [new LegacyType(LegacyType::BUILTIN_TYPE_STRING)])]],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider provideLegacyCollectionTypes
     */
    public function testExtractCollectionLegacy($property, ?array $type = null)
    {
        $this->testExtractLegacy($property, $type);
    }

    public static function provideLegacyCollectionTypes()
    {
        return [
            ['iteratorCollection', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'Iterator', true, null, new LegacyType(LegacyType::BUILTIN_TYPE_STRING))]],
            ['iteratorCollectionWithKey', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'Iterator', true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_STRING))]],
            [
                'nestedIterators',
                [new LegacyType(
                    LegacyType::BUILTIN_TYPE_OBJECT,
                    false,
                    'Iterator',
                    true,
                    new LegacyType(LegacyType::BUILTIN_TYPE_INT),
                    new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'Iterator', true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_STRING))
                )],
            ],
            [
                'arrayWithKeys',
                [new LegacyType(
                    LegacyType::BUILTIN_TYPE_ARRAY,
                    false,
                    null,
                    true,
                    new LegacyType(LegacyType::BUILTIN_TYPE_STRING),
                    new LegacyType(LegacyType::BUILTIN_TYPE_STRING)
                )],
            ],
            [
                'arrayWithKeysAndComplexValue',
                [new LegacyType(
                    LegacyType::BUILTIN_TYPE_ARRAY,
                    false,
                    null,
                    true,
                    new LegacyType(LegacyType::BUILTIN_TYPE_STRING),
                    new LegacyType(
                        LegacyType::BUILTIN_TYPE_ARRAY,
                        true,
                        null,
                        true,
                        new LegacyType(LegacyType::BUILTIN_TYPE_INT),
                        new LegacyType(LegacyType::BUILTIN_TYPE_STRING, true)
                    )
                )],
            ],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider provideLegacyTypesWithCustomPrefixes
     */
    public function testExtractTypesWithCustomPrefixesLegacy($property, ?array $type = null)
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $customExtractor = new PhpStanExtractor(['add', 'remove'], ['is', 'can']);

        $this->assertEquals($type, $customExtractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', $property));
    }

    public static function provideLegacyTypesWithCustomPrefixes()
    {
        return [
            ['foo', null],
            ['bar', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING)]],
            ['baz', [new LegacyType(LegacyType::BUILTIN_TYPE_INT)]],
            ['foo2', [new LegacyType(LegacyType::BUILTIN_TYPE_FLOAT)]],
            ['foo3', [new LegacyType(LegacyType::BUILTIN_TYPE_CALLABLE)]],
            ['foo4', [new LegacyType(LegacyType::BUILTIN_TYPE_NULL)]],
            ['foo5', null],
            [
                'files',
                [
                    new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'SplFileInfo')),
                    new LegacyType(LegacyType::BUILTIN_TYPE_RESOURCE),
                ],
            ],
            ['bal', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable')]],
            ['parent', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')]],
            ['collection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable'))]],
            ['nestedCollection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_STRING, false)))]],
            ['mixedCollection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, [new LegacyType(LegacyType::BUILTIN_TYPE_INT)], null)]],
            ['a', null],
            ['b', null],
            ['c', [new LegacyType(LegacyType::BUILTIN_TYPE_BOOL, true)]],
            ['d', [new LegacyType(LegacyType::BUILTIN_TYPE_BOOL)]],
            ['e', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_RESOURCE))]],
            ['f', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable'))]],
            ['g', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, true, null, true)]],
            ['h', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, true)]],
            ['j', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, true, 'DateTimeImmutable')]],
            ['nullableCollectionOfNonNullableElements', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, true, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_INT, false))]],
            ['donotexist', null],
            ['staticGetter', null],
            ['staticSetter', null],
        ];
    }

    public static function provideLegacyTypesWithNoPrefixes()
    {
        return [
            ['foo', null],
            ['bar', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING)]],
            ['baz', [new LegacyType(LegacyType::BUILTIN_TYPE_INT)]],
            ['foo2', [new LegacyType(LegacyType::BUILTIN_TYPE_FLOAT)]],
            ['foo3', [new LegacyType(LegacyType::BUILTIN_TYPE_CALLABLE)]],
            ['foo4', [new LegacyType(LegacyType::BUILTIN_TYPE_NULL)]],
            ['foo5', null],
            [
                'files',
                [
                    new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'SplFileInfo')),
                    new LegacyType(LegacyType::BUILTIN_TYPE_RESOURCE),
                ],
            ],
            ['bal', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable')]],
            ['parent', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')]],
            ['collection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable'))]],
            ['nestedCollection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_STRING, false)))]],
            ['mixedCollection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, [new LegacyType(LegacyType::BUILTIN_TYPE_INT)], null)]],
            ['a', null],
            ['b', null],
            ['c', null],
            ['d', null],
            ['e', null],
            ['f', null],
            ['g', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, true, null, true)]],
            ['h', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, true)]],
            ['j', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, true, 'DateTimeImmutable')]],
            ['nullableCollectionOfNonNullableElements', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, true, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_INT, false))]],
            ['donotexist', null],
            ['staticGetter', null],
            ['staticSetter', null],
        ];
    }

    public static function provideLegacyDockBlockFallbackTypes()
    {
        return [
            'pub' => [
                'pub', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING)],
            ],
            'protAcc' => [
                'protAcc', [new LegacyType(LegacyType::BUILTIN_TYPE_INT)],
            ],
            'protMut' => [
                'protMut', [new LegacyType(LegacyType::BUILTIN_TYPE_BOOL)],
            ],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider provideLegacyDockBlockFallbackTypes
     */
    public function testDocBlockFallbackLegacy($property, $types)
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $this->assertEquals($types, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\DockBlockFallback', $property));
    }

    /**
     * @group legacy
     *
     * @dataProvider provideLegacyPropertiesDefinedByTraits
     */
    public function testPropertiesDefinedByTraitsLegacy(string $property, LegacyType $type)
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $this->assertEquals([$type], $this->extractor->getTypes(DummyUsingTrait::class, $property));
    }

    public static function provideLegacyPropertiesDefinedByTraits(): array
    {
        return [
            ['propertyInTraitPrimitiveType', new LegacyType(LegacyType::BUILTIN_TYPE_STRING)],
            ['propertyInTraitObjectSameNamespace', new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, DummyUsedInTrait::class)],
            ['propertyInTraitObjectDifferentNamespace', new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Dummy::class)],
            ['dummyInAnotherNamespace', new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, DummyInAnotherNamespace::class)],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider provideLegacyPropertiesStaticType
     */
    public function testPropertiesStaticTypeLegacy(string $class, string $property, LegacyType $type)
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $this->assertEquals([$type], $this->extractor->getTypes($class, $property));
    }

    public static function provideLegacyPropertiesStaticType(): array
    {
        return [
            [ParentDummy::class, 'propertyTypeStatic', new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, ParentDummy::class)],
            [Dummy::class, 'propertyTypeStatic', new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Dummy::class)],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider provideLegacyPropertiesParentType
     */
    public function testPropertiesParentTypeLegacy(string $class, string $property, ?array $types)
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $this->assertEquals($types, $this->extractor->getTypes($class, $property));
    }

    public static function provideLegacyPropertiesParentType(): array
    {
        return [
            [ParentDummy::class, 'parentAnnotationNoParent', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'parent')]],
            [Dummy::class, 'parentAnnotation', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, ParentDummy::class)]],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider provideLegacyConstructorTypes
     */
    public function testExtractConstructorTypesLegacy($property, ?array $type = null)
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypesFromConstructor()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypeFromConstructor()" instead.');

        $this->assertEquals($type, $this->extractor->getTypesFromConstructor('Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummy', $property));
    }

    /**
     * @group legacy
     *
     * @dataProvider provideLegacyConstructorTypes
     */
    public function testExtractConstructorTypesReturnNullOnEmptyDocBlockLegacy($property)
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypesFromConstructor()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypeFromConstructor()" instead.');

        $this->assertNull($this->extractor->getTypesFromConstructor(ConstructorDummyWithoutDocBlock::class, $property));
    }

    public static function provideLegacyConstructorTypes()
    {
        return [
            ['date', [new LegacyType(LegacyType::BUILTIN_TYPE_INT)]],
            ['timezone', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeZone')]],
            ['dateObject', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeInterface')]],
            ['dateTime', null],
            ['ddd', null],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider provideLegacyUnionTypes
     */
    public function testExtractorUnionTypesLegacy(string $property, ?array $types)
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $this->assertEquals($types, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\DummyUnionType', $property));
    }

    public static function provideLegacyUnionTypes(): array
    {
        return [
            ['a', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING), new LegacyType(LegacyType::BUILTIN_TYPE_INT)]],
            ['b', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, [new LegacyType(LegacyType::BUILTIN_TYPE_INT)], [new LegacyType(LegacyType::BUILTIN_TYPE_STRING), new LegacyType(LegacyType::BUILTIN_TYPE_INT)])]],
            ['c', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, [], [new LegacyType(LegacyType::BUILTIN_TYPE_STRING), new LegacyType(LegacyType::BUILTIN_TYPE_INT)])]],
            ['d', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, [new LegacyType(LegacyType::BUILTIN_TYPE_STRING), new LegacyType(LegacyType::BUILTIN_TYPE_INT)], [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, [], [new LegacyType(LegacyType::BUILTIN_TYPE_STRING)])])]],
            ['e', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, true, Dummy::class, false, [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, [], [new LegacyType(LegacyType::BUILTIN_TYPE_STRING)])], [new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, [new LegacyType(LegacyType::BUILTIN_TYPE_INT)], [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, \Traversable::class, true, [], [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, DefaultValue::class)])])]), new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, ParentDummy::class)]],
            ['f', null],
            ['g', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, [], [new LegacyType(LegacyType::BUILTIN_TYPE_STRING), new LegacyType(LegacyType::BUILTIN_TYPE_INT)])]],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider provideLegacyPseudoTypes
     */
    public function testPseudoTypesLegacy($property, array $type)
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $this->assertEquals($type, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\PhpStanPseudoTypesDummy', $property));
    }

    public static function provideLegacyPseudoTypes(): array
    {
        return [
            ['classString', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, false, null)]],
            ['classStringGeneric', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, false, null)]],
            ['htmlEscapedString', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, false, null)]],
            ['lowercaseString', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, false, null)]],
            ['nonEmptyLowercaseString', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, false, null)]],
            ['nonEmptyString', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, false, null)]],
            ['numericString', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, false, null)]],
            ['traitString', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, false, null)]],
            ['interfaceString', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, false, null)]],
            ['literalString', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, false, null)]],
            ['positiveInt', [new LegacyType(LegacyType::BUILTIN_TYPE_INT, false, null)]],
            ['negativeInt', [new LegacyType(LegacyType::BUILTIN_TYPE_INT, false, null)]],
            ['nonPositiveInt', [new LegacyType(LegacyType::BUILTIN_TYPE_INT, false, null)]],
            ['nonNegativeInt', [new LegacyType(LegacyType::BUILTIN_TYPE_INT, false, null)]],
            ['nonZeroInt', [new LegacyType(LegacyType::BUILTIN_TYPE_INT, false, null)]],
            ['nonEmptyArray', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true)]],
            ['nonEmptyList', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT))]],
            ['scalar', [new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_FLOAT), new LegacyType(LegacyType::BUILTIN_TYPE_STRING), new LegacyType(LegacyType::BUILTIN_TYPE_BOOL)]],
            ['number', [new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_FLOAT)]],
            ['numeric', [new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_FLOAT), new LegacyType(LegacyType::BUILTIN_TYPE_STRING)]],
            ['arrayKey', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING), new LegacyType(LegacyType::BUILTIN_TYPE_INT)]],
            ['double', [new LegacyType(LegacyType::BUILTIN_TYPE_FLOAT)]],
        ];
    }

    /**
     * @group legacy
     */
    public function testDummyNamespaceLegacy()
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $this->assertEquals(
            [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy')],
            $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\DummyNamespace', 'dummy')
        );
    }

    /**
     * @group legacy
     */
    public function testDummyNamespaceWithPropertyLegacy()
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $phpStanTypes = $this->extractor->getTypes(\B\Dummy::class, 'property');
        $phpDocTypes = $this->phpDocExtractor->getTypes(\B\Dummy::class, 'property');

        $this->assertEquals('A\Property', $phpStanTypes[0]->getClassName());
        $this->assertEquals($phpDocTypes[0]->getClassName(), $phpStanTypes[0]->getClassName());
    }

    /**
     * @group legacy
     *
     * @dataProvider provideLegacyIntRangeType
     */
    public function testExtractorIntRangeTypeLegacy(string $property, ?array $types)
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $this->assertEquals($types, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\IntRangeDummy', $property));
    }

    public static function provideLegacyIntRangeType(): array
    {
        return [
            ['a', [new LegacyType(LegacyType::BUILTIN_TYPE_INT)]],
            ['b', [new LegacyType(LegacyType::BUILTIN_TYPE_INT, true)]],
            ['c', [new LegacyType(LegacyType::BUILTIN_TYPE_INT)]],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider provideLegacyPhp80Types
     */
    public function testExtractPhp80TypeLegacy(string $class, $property, ?array $type = null)
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $this->assertEquals($type, $this->extractor->getTypes($class, $property, []));
    }

    public static function provideLegacyPhp80Types()
    {
        return [
            [Php80Dummy::class, 'promotedWithDocCommentAndType', [new LegacyType(LegacyType::BUILTIN_TYPE_INT)]],
            [Php80Dummy::class, 'promotedWithDocComment', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING)]],
            [Php80Dummy::class, 'promotedAndMutated', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING)]],
            [Php80Dummy::class, 'promoted', null],
            [Php80Dummy::class, 'collection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, collection: true, collectionValueType: new LegacyType(LegacyType::BUILTIN_TYPE_STRING))]],
            [Php80PromotedDummy::class, 'promoted', null],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider allowPrivateAccessLegacyProvider
     */
    public function testAllowPrivateAccessLegacy(bool $allowPrivateAccess, array $expectedTypes)
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $extractor = new PhpStanExtractor(allowPrivateAccess: $allowPrivateAccess);
        $this->assertEquals(
            $expectedTypes,
            $extractor->getTypes(DummyPropertyAndGetterWithDifferentTypes::class, 'foo')
        );
    }

    public static function allowPrivateAccessLegacyProvider(): array
    {
        return [
            [true, [new LegacyType('string')]],
            [false, [new LegacyType('array', collection: true, collectionKeyType: new LegacyType('int'), collectionValueType: new LegacyType('string'))]],
        ];
    }

    /**
     * @group legacy
     *
     * @param list<LegacyType> $expectedTypes
     *
     * @dataProvider legacyGenericsProvider
     */
    public function testGenericsLegacy(string $property, array $expectedTypes)
    {
        $this->expectDeprecation('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::getType()" instead.');

        $this->assertEquals($expectedTypes, $this->extractor->getTypes(DummyGeneric::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: list<LegacyType>}>
     */
    public static function legacyGenericsProvider(): iterable
    {
        yield [
            'basicClass',
            [
                new LegacyType(
                    builtinType: LegacyType::BUILTIN_TYPE_OBJECT,
                    class: Clazz::class,
                    collectionValueType: new LegacyType(
                        builtinType: LegacyType::BUILTIN_TYPE_OBJECT,
                        class: Dummy::class,
                    )
                ),
            ],
        ];
        yield [
            'nullableClass',
            [
                new LegacyType(
                    builtinType: LegacyType::BUILTIN_TYPE_OBJECT,
                    class: Clazz::class,
                    nullable: true,
                    collectionValueType: new LegacyType(
                        builtinType: LegacyType::BUILTIN_TYPE_OBJECT,
                        class: Dummy::class,
                    )
                ),
            ],
        ];
        yield [
            'basicInterface',
            [
                new LegacyType(
                    builtinType: LegacyType::BUILTIN_TYPE_OBJECT,
                    class: IFace::class,
                    collectionValueType: new LegacyType(
                        builtinType: LegacyType::BUILTIN_TYPE_OBJECT,
                        class: Dummy::class,
                    )
                ),
            ],
        ];
        yield [
            'nullableInterface',
            [
                new LegacyType(
                    builtinType: LegacyType::BUILTIN_TYPE_OBJECT,
                    class: IFace::class,
                    nullable: true,
                    collectionValueType: new LegacyType(
                        builtinType: LegacyType::BUILTIN_TYPE_OBJECT,
                        class: Dummy::class,
                    )
                ),
            ],
        ];
    }

    /**
     * @dataProvider typesProvider
     */
    public function testExtract(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(Dummy::class, $property));
    }

    public static function typesProvider(): iterable
    {
        yield ['foo', null];
        yield ['bar', Type::string()];
        yield ['baz', Type::int()];
        yield ['foo2', Type::float()];
        yield ['foo3', Type::callable()];
        yield ['foo5', Type::mixed()];
        yield ['files', Type::union(Type::list(Type::object(\SplFileInfo::class)), Type::resource()), null, null];
        yield ['bal', Type::object(\DateTimeImmutable::class)];
        yield ['parent', Type::object(ParentDummy::class)];
        yield ['collection', Type::list(Type::object(\DateTimeImmutable::class))];
        yield ['nestedCollection', Type::list(Type::list(Type::string()))];
        yield ['mixedCollection', Type::list()];
        yield ['a', Type::int()];
        yield ['b', Type::nullable(Type::object(ParentDummy::class))];
        yield ['c', Type::nullable(Type::bool())];
        yield ['d', Type::bool()];
        yield ['e', Type::list(Type::resource())];
        yield ['f', Type::list(Type::object(\DateTimeImmutable::class))];
        yield ['g', Type::nullable(Type::array())];
        yield ['h', Type::nullable(Type::string())];
        yield ['i', Type::union(Type::int(), Type::string(), Type::null())];
        yield ['j', Type::nullable(Type::object(\DateTimeImmutable::class))];
        yield ['nullableCollectionOfNonNullableElements', Type::nullable(Type::list(Type::int()))];
        yield ['donotexist', null];
        yield ['staticGetter', null];
        yield ['staticSetter', null];
        yield ['emptyVar', null];
        yield ['arrayWithKeys', Type::dict(Type::string())];
        yield ['arrayOfMixed', Type::dict(Type::mixed())];
        yield ['listOfStrings', Type::list(Type::string())];
        yield ['self', Type::object(Dummy::class)];
        yield ['rootDummyItems', Type::list(Type::object(RootDummyItem::class))];
        yield ['rootDummyItem', Type::object(RootDummyItem::class)];
        yield ['collectionAsObject', Type::collection(Type::object(DummyCollection::class), Type::string(), Type::int())];
    }

    public function testParamTagTypeIsOmitted()
    {
        $this->assertNull($this->extractor->getType(PhpStanOmittedParamTagTypeDocBlock::class, 'omittedType'));
    }

    /**
     * @dataProvider invalidTypesProvider
     */
    public function testInvalid(string $property)
    {
        $this->assertNull($this->extractor->getType(InvalidDummy::class, $property));
    }

    /**
     * @return iterable<array{0: string}>
     */
    public static function invalidTypesProvider(): iterable
    {
        yield 'pub' => ['pub'];
        yield 'stat' => ['stat'];
        yield 'foo' => ['foo'];
        yield 'bar' => ['bar'];
        yield 'baz' => ['baz'];
    }

    /**
     * @dataProvider typesWithNoPrefixesProvider
     */
    public function testExtractTypesWithNoPrefixes(string $property, ?Type $type)
    {
        $noPrefixExtractor = new PhpStanExtractor([], [], []);

        $this->assertEquals($type, $noPrefixExtractor->getType(Dummy::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function typesWithNoPrefixesProvider(): iterable
    {
        yield ['foo', null];
        yield ['bar', Type::string()];
        yield ['baz', Type::int()];
        yield ['foo2', Type::float()];
        yield ['foo3', Type::callable()];
        yield ['foo5', Type::mixed()];
        yield ['files', Type::union(Type::list(Type::object(\SplFileInfo::class)), Type::resource())];
        yield ['bal', Type::object(\DateTimeImmutable::class)];
        yield ['parent', Type::object(ParentDummy::class)];
        yield ['collection', Type::list(Type::object(\DateTimeImmutable::class))];
        yield ['nestedCollection', Type::list(Type::list(Type::string()))];
        yield ['mixedCollection', Type::list()];
        yield ['a', null];
        yield ['b', null];
        yield ['c', null];
        yield ['d', null];
        yield ['e', null];
        yield ['f', null];
        yield ['g', Type::nullable(Type::array())];
        yield ['h', Type::nullable(Type::string())];
        yield ['i', Type::union(Type::int(), Type::string(), Type::null())];
        yield ['j', Type::nullable(Type::object(\DateTimeImmutable::class))];
        yield ['nullableCollectionOfNonNullableElements', Type::nullable(Type::list(Type::int()))];
        yield ['donotexist', null];
        yield ['staticGetter', null];
        yield ['staticSetter', null];
    }

    /**
     * @dataProvider provideCollectionTypes
     */
    public function testExtractCollection($property, ?Type $type)
    {
        $this->testExtract($property, $type);
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function provideCollectionTypes(): iterable
    {
        yield ['iteratorCollection', Type::collection(Type::object(\Iterator::class), Type::string())];
        yield ['iteratorCollectionWithKey', Type::collection(Type::object(\Iterator::class), Type::string(), Type::int())];
        yield ['nestedIterators', Type::collection(Type::object(\Iterator::class), Type::collection(Type::object(\Iterator::class), Type::string(), Type::int()), Type::int())];
        yield ['arrayWithKeys', Type::dict(Type::string()), null, null];
        yield ['arrayWithKeysAndComplexValue', Type::dict(Type::nullable(Type::array(Type::nullable(Type::string()), Type::int()))), null, null];
    }

    /**
     * @dataProvider typesWithCustomPrefixesProvider
     */
    public function testExtractTypesWithCustomPrefixes(string $property, ?Type $type)
    {
        $customExtractor = new PhpStanExtractor(['add', 'remove'], ['is', 'can']);

        $this->assertEquals($type, $customExtractor->getType(Dummy::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function typesWithCustomPrefixesProvider(): iterable
    {
        yield ['foo', null];
        yield ['bar', Type::string()];
        yield ['baz', Type::int()];
        yield ['foo2', Type::float()];
        yield ['foo3', Type::callable()];
        yield ['foo5', Type::mixed()];
        yield ['files', Type::union(Type::list(Type::object(\SplFileInfo::class)), Type::resource())];
        yield ['bal', Type::object(\DateTimeImmutable::class)];
        yield ['parent', Type::object(ParentDummy::class)];
        yield ['collection', Type::list(Type::object(\DateTimeImmutable::class))];
        yield ['nestedCollection', Type::list(Type::list(Type::string()))];
        yield ['mixedCollection', Type::list()];
        yield ['a', null];
        yield ['b', null];
        yield ['c', Type::nullable(Type::bool())];
        yield ['d', Type::bool()];
        yield ['e', Type::list(Type::resource())];
        yield ['f', Type::list(Type::object(\DateTimeImmutable::class))];
        yield ['g', Type::nullable(Type::array())];
        yield ['h', Type::nullable(Type::string())];
        yield ['i', Type::union(Type::int(), Type::string(), Type::null())];
        yield ['j', Type::nullable(Type::object(\DateTimeImmutable::class))];
        yield ['nullableCollectionOfNonNullableElements', Type::nullable(Type::list(Type::int()))];
        yield ['nonNullableCollectionOfNullableElements', Type::array(Type::nullable(Type::int()))];
        yield ['nullableCollectionOfMultipleNonNullableElementTypes', Type::nullable(Type::array(Type::union(Type::int(), Type::string())))];
        yield ['donotexist', null];
        yield ['staticGetter', null];
        yield ['staticSetter', null];
    }

    /**
     * @dataProvider dockBlockFallbackTypesProvider
     */
    public function testDocBlockFallback(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(DockBlockFallback::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function dockBlockFallbackTypesProvider(): iterable
    {
        yield ['pub', Type::string()];
        yield ['protAcc', Type::int()];
        yield ['protMut', Type::bool()];
    }

    /**
     * @dataProvider propertiesDefinedByTraitsProvider
     */
    public function testPropertiesDefinedByTraits(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(DummyUsingTrait::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function propertiesDefinedByTraitsProvider(): iterable
    {
        yield ['propertyInTraitPrimitiveType', Type::string()];
        yield ['propertyInTraitObjectSameNamespace', Type::object(DummyUsedInTrait::class)];
        yield ['propertyInTraitObjectDifferentNamespace', Type::object(Dummy::class)];
        yield ['dummyInAnotherNamespace', Type::object(DummyInAnotherNamespace::class)];
    }

    /**
     * @dataProvider propertiesStaticTypeProvider
     */
    public function testPropertiesStaticType(string $class, string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType($class, $property));
    }

    /**
     * @return iterable<array{0: class-string, 1: string, 2: ?Type}>
     */
    public static function propertiesStaticTypeProvider(): iterable
    {
        yield [ParentDummy::class, 'propertyTypeStatic', Type::object(ParentDummy::class)];
        yield [Dummy::class, 'propertyTypeStatic', Type::object(Dummy::class)];
    }

    public function testPropertiesParentType()
    {
        $this->assertEquals(Type::object(ParentDummy::class), $this->extractor->getType(Dummy::class, 'parentAnnotation'));
    }

    public function testPropertiesParentTypeThrowWithoutParent()
    {
        $this->expectException(LogicException::class);
        $this->extractor->getType(ParentDummy::class, 'parentAnnotationNoParent');
    }

    /**
     * @dataProvider constructorTypesProvider
     */
    public function testExtractConstructorTypes(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getTypeFromConstructor(ConstructorDummy::class, $property));
    }

    /**
     * @dataProvider constructorTypesProvider
     */
    public function testExtractConstructorTypesReturnNullOnEmptyDocBlock(string $property)
    {
        $this->assertNull($this->extractor->getTypeFromConstructor(ConstructorDummyWithoutDocBlock::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function constructorTypesProvider(): iterable
    {
        yield ['date', Type::int()];
        yield ['timezone', Type::object(\DateTimeZone::class)];
        yield ['dateObject', Type::object(\DateTimeInterface::class)];
        yield ['dateTime', null];
        yield ['ddd', null];
    }

    /**
     * @dataProvider unionTypesProvider
     */
    public function testExtractorUnionTypes(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(DummyUnionType::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function unionTypesProvider(): iterable
    {
        yield ['a', Type::union(Type::string(), Type::int())];
        yield ['b', Type::list(Type::union(Type::string(), Type::int()))];
        yield ['c', Type::array(Type::union(Type::string(), Type::int()))];
        yield ['d', Type::array(Type::array(Type::string()), Type::union(Type::string(), Type::int()))];
        yield ['e', Type::union(
            Type::generic(
                Type::object(Dummy::class),
                Type::array(Type::string(), Type::mixed()),
                Type::union(
                    Type::int(),
                    Type::list(Type::collection(Type::object(\Traversable::class), Type::object(DefaultValue::class))),
                ),
            ),
            Type::object(ParentDummy::class),
            Type::null(),
        )];
        yield ['f', null];
        yield ['g', Type::array(Type::union(Type::string(), Type::int()))];
    }

    /**
     * @dataProvider pseudoTypesProvider
     */
    public function testPseudoTypes(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(PhpStanPseudoTypesDummy::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function pseudoTypesProvider(): iterable
    {
        yield ['classString', Type::string()];

        // BC layer for type-info < 7.2
        if (!interface_exists(WrappingTypeInterface::class)) {
            yield ['classStringGeneric', Type::generic(Type::string(), Type::object(\stdClass::class))];
        } else {
            yield ['classStringGeneric', Type::string()];
        }

        yield ['htmlEscapedString', Type::string()];
        yield ['lowercaseString', Type::string()];
        yield ['nonEmptyLowercaseString', Type::string()];
        yield ['nonEmptyString', Type::string()];
        yield ['numericString', Type::string()];
        yield ['traitString', Type::string()];
        yield ['interfaceString', Type::string()];
        yield ['literalString', Type::string()];
        yield ['positiveInt', Type::int()];
        yield ['negativeInt', Type::int()];
        yield ['nonEmptyArray', Type::array()];
        yield ['nonEmptyList', Type::list()];
        yield ['scalar', Type::union(Type::int(), Type::float(), Type::string(), Type::bool())];
        yield ['number', Type::union(Type::int(), Type::float())];
        yield ['numeric', Type::union(Type::int(), Type::float(), Type::string())];
        yield ['arrayKey', Type::union(Type::int(), Type::string())];
        yield ['double', Type::float()];
    }

    public function testDummyNamespace()
    {
        $this->assertEquals(Type::object(Dummy::class), $this->extractor->getType(DummyNamespace::class, 'dummy'));
    }

    public function testDummyNamespaceWithProperty()
    {
        $phpStanType = $this->extractor->getType(\B\Dummy::class, 'property');
        $phpDocType = $this->phpDocExtractor->getType(\B\Dummy::class, 'property');

        $this->assertEquals('A\Property', $phpStanType->getClassName());
        $this->assertEquals($phpDocType->getClassName(), $phpStanType->getClassName());
    }

    /**
     * @dataProvider intRangeTypeProvider
     */
    public function testExtractorIntRangeType(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(IntRangeDummy::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function intRangeTypeProvider(): iterable
    {
        yield ['a', Type::int()];
        yield ['b', Type::nullable(Type::int())];
        yield ['c', Type::int()];
    }

    /**
     * @dataProvider php80TypesProvider
     */
    public function testExtractPhp80Type(string $class, string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType($class, $property));
    }

    /**
     * @return iterable<array{0: class-string, 1: string, 2: ?Type}>
     */
    public static function php80TypesProvider(): iterable
    {
        yield [Php80Dummy::class, 'promotedAndMutated', Type::string()];
        yield [Php80Dummy::class, 'promoted', null];
        yield [Php80Dummy::class, 'collection', Type::array(Type::string())];
        yield [Php80PromotedDummy::class, 'promoted', null];
    }

    /**
     * @dataProvider allowPrivateAccessProvider
     */
    public function testAllowPrivateAccess(bool $allowPrivateAccess, Type $expectedType)
    {
        $extractor = new PhpStanExtractor(allowPrivateAccess: $allowPrivateAccess);

        $this->assertEquals($expectedType, $extractor->getType(DummyPropertyAndGetterWithDifferentTypes::class, 'foo'));
    }

    public static function allowPrivateAccessProvider(): array
    {
        return [
            [true, Type::string()],
            [false, Type::array(Type::string(), Type::int())],
        ];
    }

    public function testGenericInterface()
    {
        $this->assertEquals(
            Type::generic(Type::enum(\BackedEnum::class), Type::string()),
            $this->extractor->getType(Dummy::class, 'genericInterface'),
        );
    }

    /**
     * @dataProvider genericsProvider
     */
    public function testGenerics(string $property, Type $expectedType)
    {
        $this->assertEquals($expectedType, $this->extractor->getType(DummyGeneric::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: Type}>
     */
    public static function genericsProvider(): iterable
    {
        yield [
            'basicClass',
            Type::generic(Type::object(Clazz::class), Type::object(Dummy::class)),
        ];
        yield [
            'nullableClass',
            Type::nullable(Type::generic(Type::object(Clazz::class), Type::object(Dummy::class))),
        ];
        yield [
            'basicInterface',
            Type::generic(Type::object(IFace::class), Type::object(Dummy::class)),
        ];
        yield [
            'nullableInterface',
            Type::nullable(Type::generic(Type::object(IFace::class), Type::object(Dummy::class))),
        ];
    }

    /**
     * @dataProvider descriptionsProvider
     */
    public function testGetDescriptions(string $property, ?string $shortDescription, ?string $longDescription)
    {
        $this->assertEquals($shortDescription, $this->extractor->getShortDescription(Dummy::class, $property));
        $this->assertEquals($longDescription, $this->extractor->getLongDescription(Dummy::class, $property));
    }

    public static function descriptionsProvider(): iterable
    {
        yield ['foo', 'Short description.', 'Long description.'];
        yield ['bar', 'This is bar', null];
        yield ['baz', 'Should be used.', null];
        yield ['bal', 'A short description ignoring template.', "A long description...\n\n...over several lines."];
        yield ['foo2', null, null];
    }
}

class PhpStanOmittedParamTagTypeDocBlock
{
    /**
     * The type is omitted here to ensure that the extractor doesn't choke on missing types.
     */
    public function setOmittedType(array $omittedTagType)
    {
    }
}
