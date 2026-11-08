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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Clazz;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummyWithoutDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummyWithPropertyDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummyWithUnsupportedType;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummyWithVarTagsDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DefaultValue;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DockBlockFallback;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyCollection;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyGeneric;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyNamespace;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyPropertyAndGetterWithDifferentTypes;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyUnionType;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyWithTemplateAndParent;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\ChildWithConstructorOverride;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\ChildWithoutConstructorOverride;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\DummyInDifferentNs;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\DummyWithStaticGetterInDifferentNs;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\DummyWithTemplateAndParentInDifferentNs;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\ParentWithPromotedPropertyDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\PromotedPropertiesWithDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\IFace;
use Symfony\Component\PropertyInfo\Tests\Fixtures\InheritedTemplates\ChildEnvelope;
use Symfony\Component\PropertyInfo\Tests\Fixtures\InheritedTemplates\DummyEnvelope;
use Symfony\Component\PropertyInfo\Tests\Fixtures\InheritedTemplates\DummyGrandChildEnvelope;
use Symfony\Component\PropertyInfo\Tests\Fixtures\InheritedTemplates\DummyItemUser;
use Symfony\Component\PropertyInfo\Tests\Fixtures\InheritedTemplates\ItemUser;
use Symfony\Component\PropertyInfo\Tests\Fixtures\IntRangeDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\LiteralDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\MultiParameterAdderDocDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php80Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php80PromotedDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\PhpDocInheritance;
use Symfony\Component\PropertyInfo\Tests\Fixtures\PhpStanPseudoTypesDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\RejectedCandidateDocDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\RootDummy\RootDummyItem;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\AnotherNamespace\DummyInAnotherNamespace;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyImportingDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsedInTrait;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsingConcreteGenericTrait;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsingGenericTrait;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsingGenericTraitWithSameTemplate;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsingTrait;
use Symfony\Component\PropertyInfo\Tests\Fixtures\VoidNeverReturnTypeDummy;
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContext;

require_once __DIR__.'/../Fixtures/Extractor/DummyNamespace.php';
require_once __DIR__.'/../Fixtures/InheritedTemplates.php';
require_once __DIR__.'/../Fixtures/PhpDocInheritance.php';

/**
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
class PhpStanExtractorTest extends TestCase
{
    private PhpStanExtractor $extractor;
    private PhpDocExtractor $phpDocExtractor;

    protected function setUp(): void
    {
        $this->extractor = new PhpStanExtractor(null, null, null, true, true);
        $this->phpDocExtractor = new PhpDocExtractor();
    }

    public function testAdderWithSeveralRequiredParametersIsIgnored()
    {
        $this->assertNull($this->extractor->getType(MultiParameterAdderDocDummy::class, 'link'));
    }

    public function testRejectedCandidateMethodsAreIgnored()
    {
        $this->assertNull($this->extractor->getType(RejectedCandidateDocDummy::class, 'foo'));
        $this->assertNull($this->extractor->getType(RejectedCandidateDocDummy::class, 'bar'));
    }

    #[DataProvider('typesProvider')]
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
        yield ['files', Type::union(Type::array(Type::object(\SplFileInfo::class)), Type::resource())];
        yield ['bal', Type::object(\DateTimeImmutable::class)];
        yield ['parent', Type::object(ParentDummy::class)];
        yield ['collection', Type::array(Type::object(\DateTimeImmutable::class))];
        yield ['nestedCollection', Type::array(Type::array(Type::string()))];
        yield ['mixedCollection', Type::array(Type::mixed())];
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
        yield ['nullableCollectionOfNonNullableElements', Type::nullable(Type::array(Type::int()))];
        yield ['donotexist', null];
        yield ['staticGetter', null];
        yield ['staticSetter', null];
        yield ['emptyVar', null];
        yield ['arrayWithKeys', Type::dict(Type::string())];
        yield ['arrayOfMixed', Type::dict(Type::mixed())];
        yield ['listOfStrings', Type::list(Type::string())];
        yield ['self', Type::object(Dummy::class)];
        yield ['rootDummyItems', Type::array(Type::object(RootDummyItem::class))];
        yield ['rootDummyItem', Type::object(RootDummyItem::class)];
        yield ['collectionAsObject', Type::collection(Type::object(DummyCollection::class), Type::string(), Type::int())];
    }

    public function testParamTagTypeIsOmitted()
    {
        $this->assertNull($this->extractor->getType(PhpStanOmittedParamTagTypeDocBlock::class, 'omittedType'));
    }

    #[DataProvider('invalidTypesProvider')]
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
        yield 'invalidKeyType' => ['invalidKeyType'];
    }

    #[DataProvider('typesWithNoPrefixesProvider')]
    public function testExtractTypesWithNoPrefixes(string $property, ?Type $type)
    {
        $noPrefixExtractor = new PhpStanExtractor([], [], [], true, true);

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
        yield ['files', Type::union(Type::array(Type::object(\SplFileInfo::class)), Type::resource())];
        yield ['bal', Type::object(\DateTimeImmutable::class)];
        yield ['parent', Type::object(ParentDummy::class)];
        yield ['collection', Type::array(Type::object(\DateTimeImmutable::class))];
        yield ['nestedCollection', Type::array(Type::array(Type::string()))];
        yield ['mixedCollection', Type::array(Type::mixed())];
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
        yield ['nullableCollectionOfNonNullableElements', Type::nullable(Type::array(Type::int()))];
        yield ['donotexist', null];
        yield ['staticGetter', null];
        yield ['staticSetter', null];
    }

    #[DataProvider('provideCollectionTypes')]
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
        yield ['arrayWithKeys', Type::dict(Type::string())];
        yield ['arrayWithKeysAndComplexValue', Type::dict(Type::nullable(Type::array(Type::nullable(Type::string()), Type::int())))];
    }

    #[DataProvider('typesWithCustomPrefixesProvider')]
    public function testExtractTypesWithCustomPrefixes(string $property, ?Type $type)
    {
        $customExtractor = new PhpStanExtractor(['add', 'remove'], ['is', 'can'], null, true, true);

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
        yield ['files', Type::union(Type::array(Type::object(\SplFileInfo::class)), Type::resource())];
        yield ['bal', Type::object(\DateTimeImmutable::class)];
        yield ['parent', Type::object(ParentDummy::class)];
        yield ['collection', Type::array(Type::object(\DateTimeImmutable::class))];
        yield ['nestedCollection', Type::array(Type::array(Type::string()))];
        yield ['mixedCollection', Type::array(Type::mixed())];
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
        yield ['nullableCollectionOfNonNullableElements', Type::nullable(Type::array(Type::int()))];
        yield ['nonNullableCollectionOfNullableElements', Type::array(Type::nullable(Type::int()))];
        yield ['nullableCollectionOfMultipleNonNullableElementTypes', Type::nullable(Type::array(Type::union(Type::int(), Type::string())))];
        yield ['donotexist', null];
        yield ['staticGetter', null];
        yield ['staticSetter', null];
    }

    #[DataProvider('dockBlockFallbackTypesProvider')]
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

    #[DataProvider('propertiesDefinedByTraitsProvider')]
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
        yield ['redeclaredPropertyInTraitInAnotherNamespace', Type::object(DummyUsedInTrait::class)];
    }

    #[DataProvider('methodsDefinedByTraitsProvider')]
    public function testMethodsDefinedByTraits(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(DummyUsingTrait::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function methodsDefinedByTraitsProvider(): iterable
    {
        yield ['methodInTraitPrimitiveType', Type::string()];
        yield ['methodInTraitObjectSameNamespace', Type::object(DummyUsedInTrait::class)];
        yield ['methodInTraitObjectDifferentNamespace', Type::object(Dummy::class)];
        yield ['methodInExternalTraitPrimitiveType', Type::string()];
        yield ['methodInExternalTraitObjectSameNamespace', Type::object(Dummy::class)];
        yield ['methodInExternalTraitObjectDifferentNamespace', Type::object(DummyUsedInTrait::class)];
        yield ['methodInTraitInAnotherNamespace', Type::object(DummyInAnotherNamespace::class)];
        yield ['aliasedMethodInTraitInAnotherNamespace', Type::object(DummyInAnotherNamespace::class)];
        yield ['overriddenMethodInTraitInAnotherNamespace', Type::object(DummyUsedInTrait::class)];
        yield ['abstractMethodInTraitInAnotherNamespace', Type::object(DummyInAnotherNamespace::class)];
        yield ['overriddenAbstractMethodInTraitInAnotherNamespace', Type::object(DummyUsedInTrait::class)];
        yield ['templateInTraitInAnotherNamespace', Type::list(Type::template('T'))];
        yield ['mutatorInTraitInAnotherNamespace', Type::object(DummyInAnotherNamespace::class)];
    }

    public function testConstructorDefinedByTrait()
    {
        $this->assertEquals(Type::object(DummyInAnotherNamespace::class), $this->extractor->getTypeFromConstructor(DummyUsingTrait::class, 'dummyInConstructor'));
        $this->assertEquals(Type::list(Type::template('T')), $this->extractor->getTypeFromConstructor(DummyUsingTrait::class, 'templateInConstructor'));
    }

    #[DataProvider('provideGenericTraitProperties')]
    public function testGenericTraitPropertiesKeepInheritedTemplateTypes(string $class, Type $expected)
    {
        if (!property_exists(TypeContext::class, 'inheritedTemplateTypes')) {
            $this->markTestSkipped('Inherited template types require symfony/type-info 8.2 or higher.');
        }

        $this->assertEquals($expected, $this->extractor->getType($class, 'item'));
        $this->assertEquals(Type::arrayShape(['value' => $expected, 'owner' => Type::object($class)]), $this->extractor->getType($class, 'details'));
    }

    #[DataProvider('provideGenericTraitProperties')]
    public function testGenericTraitMethodsKeepInheritedTemplateTypes(string $class, Type $expected)
    {
        if (!property_exists(TypeContext::class, 'inheritedTemplateTypes')) {
            $this->markTestSkipped('Inherited template types require symfony/type-info 8.2 or higher.');
        }

        $this->assertEquals($expected, $this->extractor->getType($class, 'value'));
        $this->assertEquals($expected, $this->extractor->getTypeFromConstructor($class, 'value'));
    }

    /**
     * @return iterable<array{class-string, Type}>
     */
    public static function provideGenericTraitProperties(): iterable
    {
        yield 'concrete argument' => [DummyUsingConcreteGenericTrait::class, Type::string()];
        yield 'renamed template' => [DummyUsingGenericTrait::class, Type::list(Type::template('U'))];
        yield 'same template name' => [DummyUsingGenericTraitWithSameTemplate::class, Type::list(Type::template('T'))];
    }

    #[DataProvider('propertiesStaticTypeProvider')]
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
        yield [DummyWithStaticGetterInDifferentNs::class, 'static', Type::object(DummyWithStaticGetterInDifferentNs::class)];
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

    #[DataProvider('constructorTypesProvider')]
    public function testExtractConstructorTypes(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getTypeFromConstructor(ConstructorDummy::class, $property));
    }

    #[DataProvider('constructorTypesProvider')]
    public function testExtractConstructorTypesReturnNullOnEmptyDocBlock(string $property, ?Type $type)
    {
        $this->assertNull($this->extractor->getTypeFromConstructor(ConstructorDummyWithoutDocBlock::class, $property));
    }

    public function testExtractConstructorTypesReturnNullOnUnsupportedType()
    {
        $this->assertNull($this->extractor->getTypeFromConstructor(ConstructorDummyWithUnsupportedType::class, 'flags'));
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

    #[DataProvider('constructorTypesWithOnlyVarTagsProvider')]
    public function testExtractConstructorTypesWithOnlyVarTags(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getTypeFromConstructor(ConstructorDummyWithVarTagsDocBlock::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function constructorTypesWithOnlyVarTagsProvider(): iterable
    {
        yield ['date', Type::int()];
        yield ['dateObject', Type::object(\DateTimeInterface::class)];
        yield ['objectsArray', Type::array(Type::object(ConstructorDummy::class))];
        yield ['dateTime', null];
        yield ['mixed', null];
        yield ['timezone', null];
    }

    #[DataProvider('constructorTypesWithPropertyDocBlockProvider')]
    public function testExtractConstructorTypesIgnoresTheDocBlockOfAPlainProperty(string $property)
    {
        $this->assertNull($this->extractor->getTypeFromConstructor(ConstructorDummyWithPropertyDocBlock::class, $property));
    }

    /**
     * @return iterable<array{0: string}>
     */
    public static function constructorTypesWithPropertyDocBlockProvider(): iterable
    {
        yield ['date'];
        yield ['objectsArray'];
    }

    #[DataProvider('constructorTypesOfParentClassProvider')]
    public function testExtractTypeFromConstructorOfParentClass(string $class, string $property, Type $type)
    {
        $this->assertEquals($type, $this->extractor->getTypeFromConstructor($class, $property));
    }

    public static function constructorTypesOfParentClassProvider(): iterable
    {
        yield [Dummy::class, 'rootDummyItem', Type::nullable(Type::object(RootDummyItem::class))];
        yield [DummyWithTemplateAndParent::class, 'items', Type::list(Type::template('T', Type::object(DummyInDifferentNs::class)))];
        yield [DummyWithTemplateAndParentInDifferentNs::class, 'items', Type::list(Type::template('T', Type::object(DummyInDifferentNs::class)))];
    }

    #[DataProvider('inheritedTemplateTypesProvider')]
    public function testExtractInheritedTemplateTypes(string $class, string $property, Type $type)
    {
        if (!property_exists(TypeContext::class, 'inheritedTemplateTypes')) {
            $this->markTestSkipped('symfony/type-info 8.2 or higher is required.');
        }

        $this->assertEquals($type, $this->extractor->getType($class, $property));
    }

    public static function inheritedTemplateTypesProvider(): iterable
    {
        yield [DummyEnvelope::class, 'data', Type::object(Dummy::class)];
        yield [DummyEnvelope::class, 'value', Type::object(Dummy::class)];
        yield [DummyEnvelope::class, 'payload', Type::object(Dummy::class)];
        yield [ChildEnvelope::class, 'data', Type::template('U')];
        yield [DummyGrandChildEnvelope::class, 'data', Type::list(Type::object(Dummy::class))];
        yield [DummyItemUser::class, 'item', Type::object(Dummy::class)];
        yield [DummyItemUser::class, 'itemValue', Type::object(Dummy::class)];
        yield [ItemUser::class, 'item', Type::template('U')];
        yield [ItemUser::class, 'itemValue', Type::template('U')];
    }

    public function testExtractInheritedTemplateTypeFromConstructor()
    {
        if (!property_exists(TypeContext::class, 'inheritedTemplateTypes')) {
            $this->markTestSkipped('symfony/type-info 8.2 or higher is required.');
        }

        $this->assertEquals(Type::object(Dummy::class), $this->extractor->getTypeFromConstructor(DummyEnvelope::class, 'payload'));
        $this->assertEquals(Type::list(Type::object(Dummy::class)), $this->extractor->getTypeFromConstructor(DummyGrandChildEnvelope::class, 'payload'));
    }

    public function testExtractConstructorTypesOfSeveralClassesWithOneExtractor()
    {
        $cases = [
            [ConstructorDummy::class, 'timezone', Type::object(\DateTimeZone::class)],
            [ConstructorDummy::class, 'date', Type::int()],
            [ConstructorDummy::class, 'dateTime', null],
            [ConstructorDummyWithoutDocBlock::class, 'date', null],
            ...self::constructorTypesOfParentClassProvider(),
            [ConstructorDummy::class, 'dateObject', Type::object(\DateTimeInterface::class)],
        ];

        foreach ([...$cases, ...$cases] as [$class, $property, $type]) {
            $this->assertEquals($type, $this->extractor->getTypeFromConstructor($class, $property), $class.'::'.$property);
        }

        foreach (self::providePromotedPropertyDocBlockTestCases() as [$class, $property, , , $type]) {
            $this->assertEquals($type, $this->extractor->getType($class, $property), $class.'::'.$property);
        }
    }

    #[DataProvider('unionTypesProvider')]
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
        yield ['b', Type::array(Type::union(Type::string(), Type::int()))];
        yield ['c', Type::array(Type::union(Type::string(), Type::int()))];
        yield ['d', Type::array(Type::array(Type::string()), Type::union(Type::string(), Type::int()))];
        yield ['e', Type::union(
            Type::generic(
                Type::object(Dummy::class),
                Type::array(Type::string()),
                Type::union(
                    Type::int(),
                    Type::array(Type::collection(Type::object(\Traversable::class), Type::object(DefaultValue::class))),
                ),
            ),
            Type::object(ParentDummy::class),
            Type::null(),
        )];
        yield ['f', Type::union(Type::string(), Type::null())];
        yield ['g', Type::array(Type::union(Type::string(), Type::int()))];
    }

    #[DataProvider('pseudoTypesProvider')]
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
        yield ['classStringGeneric', Type::string()];
        yield ['htmlEscapedString', Type::string()];
        yield ['lowercaseString', Type::string()];
        yield ['nonEmptyLowercaseString', Type::string()];
        yield ['nonEmptyString', Type::string()];
        yield ['numericString', Type::string()];
        yield ['traitString', Type::string()];
        yield ['interfaceString', Type::string()];
        yield ['literalString', Type::string()];
        yield ['positiveInt', Type::intRange(1, null)];
        yield ['negativeInt', Type::intRange(null, -1)];
        yield ['nonZeroInt', Type::union(Type::intRange(null, -1), Type::intRange(1, null))];
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

    public function testClassImportsWinOverTraitAndParentImports()
    {
        $this->assertEquals(Type::object(Dummy::class), $this->extractor->getType(DummyImportingDummy::class, 'dummy'));
        $this->assertEquals(Type::object(DummyInAnotherNamespace::class), $this->extractor->getType(DummyImportingDummy::class, 'dummyFromTrait'));
        $this->assertEquals(Type::object(DummyUsedInTrait::class), $this->extractor->getType(DummyImportingDummy::class, 'dummyFromParent'));
    }

    #[DataProvider('intRangeTypeProvider')]
    public function testExtractorIntRangeType(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(IntRangeDummy::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function intRangeTypeProvider(): iterable
    {
        yield ['a', Type::intRange(0, 100)];
        yield ['b', Type::nullable(Type::intRange(null, 100))];
        yield ['c', Type::intRange(50, null)];
    }

    #[DataProvider('literalTypeProvider')]
    public function testExtractorLiteralType(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(LiteralDummy::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function literalTypeProvider(): iterable
    {
        yield ['status', Type::union(Type::literal('draft'), Type::literal('published'))];
        yield ['shape', Type::arrayShape(['quoted key' => Type::literal("it's")])];
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testNotPassingTrueForResolveRefinementsIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 8.2: Not passing true for the "$resolveRefinements" argument of "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::__construct()" is deprecated; PHPDoc refinements will always be resolved in 9.0.');

        new PhpStanExtractor();
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testPassingFalseForResolveRefinementsIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 8.2: Not passing true for the "$resolveRefinements" argument of "Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor::__construct()" is deprecated; PHPDoc refinements will always be resolved in 9.0.');

        $this->assertEquals(Type::int(), (new PhpStanExtractor(null, null, null, true, false))->getType(IntRangeDummy::class, 'a'));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testDoNotResolveLiteralsWhenDisabled()
    {
        $extractor = new PhpStanExtractor(null, null, null, true, false);

        $this->assertEquals(Type::string(), $extractor->getType(LiteralDummy::class, 'status'));
        $this->assertEquals(Type::arrayShape(['quoted key' => Type::string()]), $extractor->getType(LiteralDummy::class, 'shape'));
    }

    #[DataProvider('php80TypesProvider')]
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

    public function testInheritedPromotedPropertyWithConstructorOverride()
    {
        $expectedType = Type::dict(Type::int(), Type::string());

        $this->assertEquals($expectedType, $this->extractor->getType(ParentWithPromotedPropertyDocBlock::class, 'items'));
        $this->assertEquals($expectedType, $this->extractor->getType(ChildWithoutConstructorOverride::class, 'items'));
        $this->assertEquals($expectedType, $this->extractor->getType(ChildWithConstructorOverride::class, 'items'));
    }

    #[DataProvider('allowPrivateAccessProvider')]
    public function testAllowPrivateAccess(bool $allowPrivateAccess, Type $expectedType)
    {
        $extractor = new PhpStanExtractor(null, null, null, $allowPrivateAccess, true);

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
            Type::generic(Type::object(\BackedEnum::class), Type::string()),
            $this->extractor->getType(Dummy::class, 'genericInterface'),
        );
    }

    #[DataProvider('genericsProvider')]
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
        yield [
            'twoGenerics',
            Type::generic(Type::object(Clazz::class), Type::int(), Type::object(Dummy::class)),
        ];
        yield [
            'threeGenerics',
            Type::generic(Type::object(Clazz::class), Type::int(), Type::object(Dummy::class), Type::string()),
        ];
    }

    #[DataProvider('descriptionsProvider')]
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

    public function testSkipVoidNeverReturnTypeAccessors()
    {
        $this->assertNull($this->extractor->getType(VoidNeverReturnTypeDummy::class, 'voidProperty'));
        $this->assertNull($this->extractor->getType(VoidNeverReturnTypeDummy::class, 'neverProperty'));
        $this->assertEquals(Type::string(), $this->extractor->getType(VoidNeverReturnTypeDummy::class, 'normalProperty'));
    }

    #[DataProvider('providePromotedPropertyDocBlockTestCases')]
    public function testPromotedPropertyDocBlock(string $class, string $property, ?string $shortDescription, ?string $longDescription, ?Type $type)
    {
        $this->assertSame($shortDescription, $this->extractor->getShortDescription($class, $property));
        $this->assertSame($longDescription, $this->extractor->getLongDescription($class, $property));
        $this->assertEquals($type, $this->extractor->getType($class, $property));
    }

    public static function providePromotedPropertyDocBlockTestCases(): iterable
    {
        yield 'description from constructor @param' => [PromotedPropertiesWithDocBlock::class, 'foo', 'Just a foo property', null, Type::string()];
        yield 'promoted property with no docblock' => [PromotedPropertiesWithDocBlock::class, 'bar', null, null, null];
        yield 'description and type from inline @var' => [PromotedPropertiesWithDocBlock::class, 'baz', 'A baz property', null, Type::string()];
        yield 'inline @var wins over constructor @param' => [PromotedPropertiesWithDocBlock::class, 'qux', 'An overridden qux property', null, Type::int()];
        yield 'long description from inline docblock' => [PromotedPropertiesWithDocBlock::class, 'corge', 'A corge property.', 'A detailed explanation of corge.', null];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('inheritedTypesProvider')]
    public function testInheritedTypes(string $class, string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType($class, $property));
    }

    /**
     * @return iterable<string, array{class-string, string, ?Type}>
     */
    public static function inheritedTypesProvider(): iterable
    {
        $items = Type::list(Type::object(PhpDocInheritance\Item::class));

        yield 'parent method' => [PhpDocInheritance\ChildDummy::class, 'items', $items];
        yield 'parent method without native types' => [PhpDocInheritance\ChildDummy::class, 'untyped', $items];
        yield 'native type added by the override' => [PhpDocInheritance\ChildDummy::class, 'addedNative', $items];
        yield 'docblock with a description only' => [PhpDocInheritance\ChildDummy::class, 'described', $items];
        yield 'docblock with another tag' => [PhpDocInheritance\ChildDummy::class, 'deprecated', $items];
        yield 'inheritDoc' => [PhpDocInheritance\ChildDummy::class, 'inheritDoc', $items];
        yield 'own tag' => [PhpDocInheritance\ChildDummy::class, 'own', Type::dict(Type::object(PhpDocInheritance\Item::class))];
        yield 'nullable native type made non-nullable' => [PhpDocInheritance\ChildDummy::class, 'nullable', null];
        yield 'iterable native type made an array' => [PhpDocInheritance\ChildDummy::class, 'iterable', null];
        yield 'native self type' => [PhpDocInheritance\ChildDummy::class, 'nativeSelf', null];
        yield 'unsupported type' => [PhpDocInheritance\ChildDummy::class, 'unsupported', null];
        yield 'private parent method' => [PhpDocInheritance\ChildDummy::class, 'private', null];
        yield 'parent mutator' => [PhpDocInheritance\ChildDummy::class, 'tags', $items];
        yield 'parent mutator with a renamed parameter' => [PhpDocInheritance\ChildDummy::class, 'renamed', $items];
        yield 'parent mutator with a wider parameter' => [PhpDocInheritance\ChildDummy::class, 'widened', null];
        yield 'grandparent method' => [PhpDocInheritance\GrandChildDummy::class, 'items', $items];
        yield 'chain of overrides' => [PhpDocInheritance\GrandChildDummy::class, 'chain', $items];

        yield 'interface method' => [PhpDocInheritance\ImplementsInterface::class, 'items', $items];
        yield 'interface mutator' => [PhpDocInheritance\ImplementsInterface::class, 'tags', $items];
        yield 'interface of the parent' => [PhpDocInheritance\ChildOfImplementing::class, 'items', $items];
        yield 'interface of the parent, not overridden' => [PhpDocInheritance\ChildOfImplementing::class, 'tags', $items];
        yield 'first interface' => [PhpDocInheritance\ImplementsAB::class, 'items', Type::list(Type::object(PhpDocInheritance\A::class))];
        yield 'first interface in another order' => [PhpDocInheritance\ImplementsBA::class, 'items', Type::list(Type::object(PhpDocInheritance\B::class))];
        yield 'first documented interface' => [PhpDocInheritance\ImplementsUndocumentedFirst::class, 'items', Type::list(Type::object(PhpDocInheritance\B::class))];
        yield 'parent interface' => [PhpDocInheritance\ImplementsSub::class, 'items', Type::list(Type::object(PhpDocInheritance\A::class))];
        yield 'parent before interface' => [PhpDocInheritance\ParentAImplementsB::class, 'items', Type::list(Type::object(PhpDocInheritance\A::class))];
        yield 'interface after a parent docblock without tag' => [PhpDocInheritance\DescribedParentAndInterface::class, 'items', $items];

        yield 'abstract trait method' => [PhpDocInheritance\ImplementsAbstractTrait::class, 'items', $items];
        yield 'private abstract trait method' => [PhpDocInheritance\ImplementsAbstractTrait::class, 'hidden', $items];
        yield 'self in an abstract trait method' => [PhpDocInheritance\ImplementsAbstractTrait::class, 'selves', Type::list(Type::object(PhpDocInheritance\ImplementsAbstractTrait::class))];
        yield 'native self type of an abstract trait method' => [PhpDocInheritance\ImplementsAbstractTrait::class, 'nativeSelf', Type::object(PhpDocInheritance\ImplementsAbstractTrait::class)];
        yield 'concrete trait method' => [PhpDocInheritance\OverridesConcreteTrait::class, 'items', null];
        yield 'trait method overriding a parent method' => [PhpDocInheritance\UndocumentedTraitWithParent::class, 'items', Type::list(Type::object(PhpDocInheritance\A::class))];
        yield 'trait method implementing an interface method' => [PhpDocInheritance\UndocumentedTraitWithInterface::class, 'items', $items];
        yield 'parent before abstract trait method' => [PhpDocInheritance\ParentVsAbstractTrait::class, 'items', Type::list(Type::object(PhpDocInheritance\A::class))];
        yield 'interface before abstract trait method' => [PhpDocInheritance\InterfaceVsAbstractTrait::class, 'items', Type::list(Type::object(PhpDocInheritance\B::class))];
        yield 'abstract trait method replaced by the parent method' => [PhpDocInheritance\ParentKeptOverAbstractTrait::class, 'items', null];
        yield 'parent method declared by a trait' => [PhpDocInheritance\ChildOfParentUsingDocumentedTrait::class, 'items', $items];

        yield 'class imported by the parent' => [PhpDocInheritance\Entity\ChildInEntity::class, 'thing', Type::object(PhpDocInheritance\Model\Sub\Thing::class)];
        yield 'class in the namespace of the parent' => [PhpDocInheritance\Entity\ChildInEntity::class, 'widget', Type::object(PhpDocInheritance\Model\Widget::class)];
        yield 'self' => [PhpDocInheritance\Entity\ChildInEntity::class, 'self', Type::object(PhpDocInheritance\Model\ParentInModel::class)];
        yield 'static' => [PhpDocInheritance\Entity\ChildInEntity::class, 'static', Type::object(PhpDocInheritance\Entity\ChildInEntity::class)];
        yield '$this' => [PhpDocInheritance\Entity\ChildInEntity::class, 'this', Type::object(PhpDocInheritance\Entity\ChildInEntity::class)];
        yield 'list of self' => [PhpDocInheritance\Entity\ChildInEntity::class, 'selfList', Type::list(Type::object(PhpDocInheritance\Model\ParentInModel::class))];
        yield 'parent' => [PhpDocInheritance\Entity\ChildInEntity::class, 'parentObject', Type::object(PhpDocInheritance\Model\GrandParentInModel::class)];
        yield 'type alias of the parent' => [PhpDocInheritance\Entity\ChildInEntity::class, 'aliased', $items];
        yield 'parent method declared by a trait in another namespace' => [PhpDocInheritance\Entity\ChildInEntity::class, 'gadget', Type::object(PhpDocInheritance\Model\Sub\Gadget::class)];

        yield 'unbound template' => [PhpDocInheritance\UnboundChild::class, 'items', Type::list(Type::template('TItem'))];

        yield 'promoted property of the parent' => [PhpDocInheritance\ChildWithoutParameter::class, 'items', $items];
        yield 'promoted property documented by the parent constructor' => [PhpDocInheritance\ChildPromotingOwn::class, 'items', $items];
        yield 'promoted property documented nowhere' => [PhpDocInheritance\ChildPromotingOwn::class, 'name', null];
        yield 'promoted property promoted again' => [PhpDocInheritance\ChildPromotingAgain::class, 'items', $items];
        yield 'property redeclaring a promoted property' => [PhpDocInheritance\ChildRedeclaringPromotedProperty::class, 'items', $items];
        yield 'promoted property declared by the parent' => [PhpDocInheritance\ChildPromotingParentProperty::class, 'items', null];

        yield 'typed property' => [PhpDocInheritance\ChildWithProperties::class, 'typed', $items];
        yield 'untyped property' => [PhpDocInheritance\ChildWithProperties::class, 'untyped', $items];
        yield 'protected property made public' => [PhpDocInheritance\ChildWithProperties::class, 'widened', $items];
        yield 'private parent property' => [PhpDocInheritance\ChildWithProperties::class, 'private', null];
        yield 'trait property declared again' => [PhpDocInheritance\ChildWithProperties::class, 'fromTrait', null];
        yield 'interface property' => [PhpDocInheritance\ImplementsPropertyInterface::class, 'items', $items];
        yield 'abstract property' => [PhpDocInheritance\ImplementsAbstractProperty::class, 'items', $items];
        yield 'property after a docblock on the use statement' => [PhpDocInheritance\AfterUseDocBlockProperty::class, 'typed', $items];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('inheritedBoundTemplateTypesProvider')]
    public function testInheritedBoundTemplateTypes(string $class, string $property)
    {
        if (!property_exists(TypeContext::class, 'inheritedTemplateTypes')) {
            $this->markTestSkipped('symfony/type-info 8.2 or higher is required.');
        }

        $this->assertEquals(Type::list(Type::object(PhpDocInheritance\Item::class)), $this->extractor->getType($class, $property));
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function inheritedBoundTemplateTypesProvider(): iterable
    {
        yield 'abstract trait method after a docblock on the use statement' => [PhpDocInheritance\AfterUseDocBlock::class, 'items'];
        yield 'template bound by the class' => [PhpDocInheritance\BoundChild::class, 'items'];
        yield 'template bound by an implementation' => [PhpDocInheritance\BoundImplementation::class, 'items'];
        yield 'template bound by a parent for its trait' => [PhpDocInheritance\ChildOfParentUsingGenericTrait::class, 'items'];
        yield 'template bound through a parent' => [PhpDocInheritance\BoundLeaf::class, 'items'];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('inheritedConstructorTypesProvider')]
    public function testInheritedConstructorTypes(string $class, string $property)
    {
        $this->assertEquals(Type::list(Type::object(PhpDocInheritance\Item::class)), $this->extractor->getTypeFromConstructor($class, $property));
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function inheritedConstructorTypesProvider(): iterable
    {
        yield 'promoted property' => [PhpDocInheritance\ChildPromotingOwn::class, 'items'];
        yield 'promoted property promoted again' => [PhpDocInheritance\ChildPromotingAgain::class, 'items'];
        yield 'parameter' => [PhpDocInheritance\ChildPlain::class, 'items'];
        yield 'parameter at another position' => [PhpDocInheritance\ChildReordered::class, 'items'];
        yield 'abstract parent constructor' => [PhpDocInheritance\ImplementsAbstractConstructor::class, 'items'];
        yield 'interface constructor' => [PhpDocInheritance\ImplementsConstructorInterface::class, 'items'];
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
