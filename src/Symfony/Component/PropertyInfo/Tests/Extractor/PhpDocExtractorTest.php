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

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\PseudoTypes\Generic;
use phpDocumentor\Reflection\PseudoTypes\IntMask;
use phpDocumentor\Reflection\PseudoTypes\IntMaskOf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Clazz;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummyWithoutDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummyWithPropertyDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummyWithVarTagsDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DockBlockFallback;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyCollection;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyGeneric;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyIntersectionType;
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
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\PromotedPropertiesWithDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\IFace;
use Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\MultiParameterAdderDocDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php80Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\PhpDocInheritance;
use Symfony\Component\PropertyInfo\Tests\Fixtures\PseudoTypeDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\PseudoTypesDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\RejectedCandidateDocDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\RootDummy\RootDummyItem;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\AnotherNamespace\DummyInAnotherNamespace;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsedInTrait;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsingTrait;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsingTraitWithUseDocBlock;
use Symfony\Component\PropertyInfo\Tests\Fixtures\VoidNeverReturnTypeDummy;
use Symfony\Component\TypeInfo\Type;

require_once __DIR__.'/../Fixtures/PhpDocInheritance.php';

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
        $this->assertNull($this->extractor->getType(MultiParameterAdderDocDummy::class, 'link'));
    }

    public function testRejectedCandidateMethodsAreIgnored()
    {
        $this->assertNull($this->extractor->getType(RejectedCandidateDocDummy::class, 'foo'));
        $this->assertNull($this->extractor->getType(RejectedCandidateDocDummy::class, 'bar'));
    }

    public function testGetDocBlock()
    {
        $docBlock = $this->extractor->getDocBlock(Dummy::class, 'g');
        $this->assertInstanceOf(DocBlock::class, $docBlock);
        $this->assertSame('Nullable array.', $docBlock->getSummary());

        $docBlock = $this->extractor->getDocBlock(Dummy::class, 'noDocBlock;');
        $this->assertNull($docBlock);

        $docBlock = $this->extractor->getDocBlock(Dummy::class, 'notAvailable');
        $this->assertNull($docBlock);
    }

    public function testReturnNullOnEmptyDocBlock()
    {
        $this->assertNull($this->extractor->getShortDescription(EmptyDocBlock::class, 'foo'));
    }

    #[DataProvider('genericsProvider')]
    public function testGenerics(string $class, string $property, Type $expectedType)
    {
        $this->assertEquals($expectedType, $this->extractor->getType($class, $property));
    }

    /**
     * @return iterable<array{0: class-string, 1: string, 2: Type}>
     */
    public static function genericsProvider(): iterable
    {
        yield [
            Dummy::class,
            'genericInterface',
            Type::generic(Type::object(\BackedEnum::class), Type::string()),
        ];
        yield [
            DummyGeneric::class,
            'basicClass',
            Type::generic(Type::object(Clazz::class), Type::object(Dummy::class)),
        ];
        yield [
            DummyGeneric::class,
            'basicInterface',
            Type::generic(Type::object(IFace::class), Type::object(Dummy::class)),
        ];
        yield [
            DummyGeneric::class,
            'twoGenerics',
            Type::generic(Type::object(Clazz::class), Type::int(), Type::object(Dummy::class)),
        ];
        yield [
            DummyGeneric::class,
            'nullableClass',
            Type::nullable(Type::generic(Type::object(Clazz::class), Type::object(Dummy::class))),
        ];
        yield [
            DummyGeneric::class,
            'nullableInterface',
            Type::nullable(Type::generic(Type::object(IFace::class), Type::object(Dummy::class))),
        ];

        // phpdocumentor/reflection-docblock >= 6
        if (class_exists(Generic::class)) {
            yield [
                DummyGeneric::class,
                'threeGenerics',
                Type::generic(Type::object(Clazz::class), Type::int(), Type::object(Dummy::class), Type::string()),
            ];
        }
    }

    public function testParamTagTypeIsOmitted()
    {
        $this->assertNull($this->extractor->getType(OmittedParamTagTypeDocBlock::class, 'omittedType'));
    }

    #[DataProvider('typeProvider')]
    public function testExtract(string $property, ?Type $type, ?string $shortDescription, ?string $longDescription)
    {
        $this->assertEquals($type, $this->extractor->getType(Dummy::class, $property));
        $this->assertSame($shortDescription, $this->extractor->getShortDescription(Dummy::class, $property));
        $this->assertSame($longDescription, $this->extractor->getLongDescription(Dummy::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type, 2: ?string, 3: ?string}>
     */
    public static function typeProvider(): iterable
    {
        yield ['foo', null, 'Short description.', 'Long description.'];
        yield ['bar', Type::string(), 'This is bar', null];
        yield ['baz', Type::int(), 'Should be used.', null];
        yield ['foo2', Type::float(), null, null];
        yield ['foo3', Type::callable(), null, null];
        yield ['foo4', Type::null(), null, null];
        yield ['foo5', Type::mixed(), null, null];
        yield ['files', Type::union(Type::array(Type::object(\SplFileInfo::class)), Type::resource()), null, null];
        yield ['bal', Type::object(\DateTimeImmutable::class), 'A short description ignoring template.', "A long description...\n\n...over several lines."];
        yield ['parent', Type::object(ParentDummy::class), null, null];
        yield ['collection', Type::array(Type::object(\DateTimeImmutable::class)), null, null];
        yield ['nestedCollection', Type::array(Type::array(Type::string())), null, null];
        yield ['mixedCollection', Type::array(), null, null];
        yield ['nullableTypedCollection', Type::nullable(Type::array(Type::object(Dummy::class))), null, null];
        yield ['unionWithMixed', Type::mixed(), null, null];
        yield ['a', Type::int(), 'A.', null];
        yield ['b', Type::nullable(Type::object(ParentDummy::class)), 'B.', null];
        yield ['c', Type::nullable(Type::bool()), null, null];
        yield ['ct', Type::nullable(Type::true()), null, null];
        yield ['cf', Type::nullable(Type::false()), null, null];
        yield ['d', Type::bool(), null, null];
        yield ['dt', Type::true(), null, null];
        yield ['df', Type::false(), null, null];
        yield ['e', Type::list(Type::resource()), null, null];
        yield ['f', Type::list(Type::object(\DateTimeImmutable::class)), null, null];
        yield ['g', Type::nullable(Type::array()), 'Nullable array.', null];
        yield ['h', Type::nullable(Type::string()), null, null];
        yield ['i', Type::nullable(Type::union(Type::int(), Type::string())), null, null];
        yield ['j', Type::nullable(Type::object(\DateTimeImmutable::class)), null, null];
        yield ['nullableCollectionOfNonNullableElements', Type::nullable(Type::array(Type::int())), null, null];
        yield ['donotexist', null, null, null];
        yield ['staticGetter', null, null, null];
        yield ['staticSetter', null, null, null];
        yield ['emptyVar', null, 'This should not be removed.', null];
        yield ['arrayWithKeys', Type::dict(Type::string()), null, null];
        yield ['arrayOfMixed', Type::dict(Type::mixed()), null, null];
        yield ['listOfStrings', Type::list(Type::string()), null, null];
        yield ['self', Type::object(Dummy::class), null, null];
        yield ['collectionAsObject', Type::collection(Type::object(DummyCollection::class), Type::string(), Type::int()), null, null];
    }

    #[DataProvider('invalidTypeProvider')]
    public function testInvalid(string $property, ?string $shortDescription, ?string $longDescription)
    {
        $this->assertNull($this->extractor->getType(InvalidDummy::class, $property));
        $this->assertSame($shortDescription, $this->extractor->getShortDescription(InvalidDummy::class, $property));
        $this->assertSame($longDescription, $this->extractor->getLongDescription(InvalidDummy::class, $property));
    }

    /**
     * @return iterable<string, array{0: string, 1: ?string, 2: ?string}>
     */
    public static function invalidTypeProvider(): iterable
    {
        yield 'pub' => ['pub', null, null];
        yield 'stat' => ['stat', null, null];
        yield 'bar' => ['bar', 'Bar.', null];
        yield 'invalidKeyType' => ['invalidKeyType', null, null];
    }

    #[DataProvider('typeWithNoPrefixesProvider')]
    public function testExtractTypesWithNoPrefixes(string $property, ?Type $type)
    {
        $noPrefixExtractor = new PhpDocExtractor(null, [], [], []);

        $this->assertEquals($type, $noPrefixExtractor->getType(Dummy::class, $property));
    }

    public static function typeWithNoPrefixesProvider()
    {
        yield ['foo', null];
        yield ['bar', Type::string()];
        yield ['baz', Type::int()];
        yield ['foo2', Type::float()];
        yield ['foo3', Type::callable()];
        yield ['foo4', Type::null()];
        yield ['foo5', Type::mixed()];
        yield ['files', Type::union(Type::array(Type::object(\SplFileInfo::class)), Type::resource())];
        yield ['bal', Type::object(\DateTimeImmutable::class)];
        yield ['parent', Type::object(ParentDummy::class)];
        yield ['collection', Type::array(Type::object(\DateTimeImmutable::class))];
        yield ['nestedCollection', Type::array(Type::array(Type::string()))];
        yield ['mixedCollection', Type::array()];
        yield ['nullableTypedCollection', Type::nullable(Type::array(Type::object(Dummy::class)))];
        yield ['unionWithMixed', Type::mixed()];
        yield ['a', null];
        yield ['b', null];
        yield ['c', null];
        yield ['d', null];
        yield ['e', null];
        yield ['f', null];
        yield ['g', Type::nullable(Type::array())];
        yield ['h', Type::nullable(Type::string())];
        yield ['i', Type::nullable(Type::union(Type::int(), Type::string()))];
        yield ['j', Type::nullable(Type::object(\DateTimeImmutable::class))];
        yield ['nullableCollectionOfNonNullableElements', Type::nullable(Type::array(Type::int()))];
        yield ['donotexist', null];
        yield ['staticGetter', null];
        yield ['staticSetter', null];
    }

    #[DataProvider('provideCollectionTypes')]
    public function testExtractCollection(string $property, ?Type $type)
    {
        $this->testExtract($property, $type, null, null);
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

    #[DataProvider('typeWithCustomPrefixesProvider')]
    public function testExtractTypeWithCustomPrefixes(string $property, ?Type $type)
    {
        $customExtractor = new PhpDocExtractor(null, ['add', 'remove'], ['is', 'can']);

        $this->assertEquals($type, $customExtractor->getType(Dummy::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function typeWithCustomPrefixesProvider(): iterable
    {
        yield ['foo', null];
        yield ['bar', Type::string()];
        yield ['baz', Type::int()];
        yield ['foo2', Type::float()];
        yield ['foo3', Type::callable()];
        yield ['foo4', Type::null()];
        yield ['foo5', Type::mixed()];
        yield ['files', Type::union(Type::array(Type::object(\SplFileInfo::class)), Type::resource())];
        yield ['bal', Type::object(\DateTimeImmutable::class)];
        yield ['parent', Type::object(ParentDummy::class)];
        yield ['collection', Type::array(Type::object(\DateTimeImmutable::class))];
        yield ['nestedCollection', Type::array(Type::array(Type::string()))];
        yield ['mixedCollection', Type::array()];
        yield ['nullableTypedCollection', Type::nullable(Type::array(Type::object(Dummy::class)))];
        yield ['unionWithMixed', Type::mixed()];
        yield ['a', null];
        yield ['b', null];
        yield ['c', Type::nullable(Type::bool())];
        yield ['d', Type::bool()];
        yield ['e', Type::list(Type::resource())];
        yield ['f', Type::list(Type::object(\DateTimeImmutable::class))];
        yield ['g', Type::nullable(Type::array())];
        yield ['h', Type::nullable(Type::string())];
        yield ['i', Type::nullable(Type::union(Type::int(), Type::string()))];
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
        yield ['propertyInExternalTraitPrimitiveType', Type::string()];
        yield ['propertyInExternalTraitObjectSameNamespace', Type::object(Dummy::class)];
        yield ['propertyInExternalTraitObjectDifferentNamespace', Type::object(DummyUsedInTrait::class)];
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
        yield ['mutatorInTraitInAnotherNamespace', Type::object(DummyInAnotherNamespace::class)];
    }

    public function testAbstractMethodInTraitAfterUseDocBlock()
    {
        $this->assertEquals(Type::object(DummyInAnotherNamespace::class), $this->extractor->getType(DummyUsingTraitWithUseDocBlock::class, 'abstractMethodInTraitInAnotherNamespace'));
    }

    public function testConstructorDefinedByTrait()
    {
        $this->assertEquals(Type::object(DummyInAnotherNamespace::class), $this->extractor->getTypeFromConstructor(DummyUsingTrait::class, 'dummyInConstructor'));
    }

    /**
     * @param class-string $class
     */
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
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('propertiesParentTypeProvider')]
    public function testPropertiesParentType(string $class, string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType($class, $property));
    }

    /**
     * @return iterable<array{0: class-string, 1: string, 2: ?Type}>
     */
    public static function propertiesParentTypeProvider(): iterable
    {
        yield [ParentDummy::class, 'parentAnnotationNoParent', Type::object('parent')];
        yield [Dummy::class, 'parentAnnotation', Type::object(ParentDummy::class)];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('selfDocBlockInNestedTypeProvider')]
    public function testSelfDocBlockInNestedTypeResolvesClass(string $class, string $property, Type $expectedType)
    {
        $this->assertEquals($expectedType, $this->extractor->getType($class, $property));
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: Type}>
     */
    public static function selfDocBlockInNestedTypeProvider(): iterable
    {
        $parent = Type::object(ParentWithSelfDocBlock::class);
        $child = Type::object(ChildWithSelfDocBlock::class);

        yield 'self collection' => [ParentWithSelfDocBlock::class, 'selfCollectionProp', Type::array($parent)];
        yield 'self collection from child' => [ChildWithSelfDocBlock::class, 'selfCollectionProp', Type::array($parent)];
        yield 'static collection' => [ParentWithSelfDocBlock::class, 'staticCollectionProp', Type::dict($parent)];
        yield 'static collection from child' => [ChildWithSelfDocBlock::class, 'staticCollectionProp', Type::dict($child)];
        yield 'parent collection' => [ChildWithSelfDocBlock::class, 'parentCollectionProp', Type::list($parent)];
        yield 'nullable self' => [ChildWithSelfDocBlock::class, 'nullableSelfProp', Type::nullable($parent)];
        yield 'self or int' => [ParentWithSelfDocBlock::class, 'selfOrIntProp', Type::union($parent, Type::int())];
        yield 'nullable self or int' => [ParentWithSelfDocBlock::class, 'nullableSelfOrIntProp', Type::nullable(Type::union($parent, Type::int()))];
        yield 'self or static' => [ParentWithSelfDocBlock::class, 'selfOrStaticProp', $parent];
        yield 'self or static from child' => [ChildWithSelfDocBlock::class, 'selfOrStaticProp', Type::union($parent, $child)];
        yield 'collection of self or int' => [ParentWithSelfDocBlock::class, 'selfOrIntCollectionProp', Type::array(Type::union($parent, Type::int()), Type::int())];
    }

    /**
     * @param class-string $class
     * @param class-string $expectedResolvedClass
     */
    #[DataProvider('selfDocBlockResolutionProvider')]
    public function testSelfDocBlockResolvesToDeclaringClass(string $class, string $property, string $expectedResolvedClass)
    {
        $this->assertEquals(Type::object($expectedResolvedClass), $this->extractor->getType($class, $property));
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

    #[DataProvider('inheritedPromotedPropertyWithConstructorOverrideProvider')]
    public function testInheritedPromotedPropertyWithConstructorOverride(string $class, string $property, ?Type $expectedType)
    {
        $this->assertEquals($expectedType, $this->extractor->getType($class, $property));
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: ?Type}>
     */
    public static function inheritedPromotedPropertyWithConstructorOverrideProvider(): iterable
    {
        $expectedItemsType = Type::dict(Type::int(), Type::string());

        yield 'parent promoted property' => [ParentWithPromotedPropertyDocBlock::class, 'items', $expectedItemsType];
        yield 'child without constructor override' => [ChildWithoutConstructorOverride::class, 'items', $expectedItemsType];
        yield 'child with constructor override' => [ChildWithConstructorOverride::class, 'items', $expectedItemsType];
    }

    public function testUnknownPseudoType()
    {
        $this->assertEquals(Type::object('Symfony\\Component\\PropertyInfo\\Tests\\Fixtures\\unknownpseudo'), $this->extractor->getType(PseudoTypeDummy::class, 'unknownPseudoType'));
    }

    public function testScalarPseudoType()
    {
        $this->assertEquals(Type::object('scalar'), $this->extractor->getType(PseudoTypeDummy::class, 'scalarPseudoType'));
    }

    public function testIntersectionTypesAreIgnored()
    {
        $this->assertNull($this->extractor->getType(DummyIntersectionType::class, 'objects'));
        $this->assertNull($this->extractor->getType(DummyIntersectionType::class, 'arrayShapes'));
        $this->assertNull($this->extractor->getType(DummyIntersectionType::class, 'nullable'));
        $this->assertNull($this->extractor->getType(DummyIntersectionType::class, 'orNull'));
        $this->assertNull($this->extractor->getType(DummyIntersectionType::class, 'orString'));
        $this->assertEquals(Type::array(), $this->extractor->getType(DummyIntersectionType::class, 'collection'));
    }

    #[DataProvider('constructorTypesProvider')]
    public function testExtractConstructorType(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getTypeFromConstructor(ConstructorDummy::class, $property));
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
        yield ['mixed', Type::mixed()];
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

    public function testExtractConstructorTypesOfSeveralClassesWithOneExtractor()
    {
        $cases = [
            [ConstructorDummy::class, 'timezone', Type::object(\DateTimeZone::class)],
            [ConstructorDummy::class, 'date', Type::int()],
            [ConstructorDummy::class, 'dateTime', null],
            [ConstructorDummyWithoutDocBlock::class, 'date', null],
            [Dummy::class, 'rootDummyItem', Type::nullable(Type::object(RootDummyItem::class))],
            [ConstructorDummy::class, 'dateObject', Type::object(\DateTimeInterface::class)],
        ];

        foreach ([...$cases, ...$cases] as [$class, $property, $type]) {
            $this->assertEquals($type, $this->extractor->getTypeFromConstructor($class, $property), $class.'::'.$property);
        }

        foreach (self::providePromotedPropertyDocBlockTestCases() as [$class, $property, , , $type]) {
            $this->assertEquals($type, $this->extractor->getType($class, $property), $class.'::'.$property);
        }
    }

    #[DataProvider('pseudoTypeProvider')]
    public function testPseudoType(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(PseudoTypesDummy::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function pseudoTypeProvider(): iterable
    {
        yield ['classString', Type::string()];
        yield ['classStringGeneric', Type::string()];
        yield ['htmlEscapedString', Type::string()];
        yield ['lowercaseString', Type::string()];
        yield ['nonEmptyLowercaseString', Type::string()];
        yield ['nonEmptyString', Type::string()];
        yield ['numericString', Type::string()];
        yield ['traitString', Type::string()];
        yield ['positiveInt', Type::int()];
        yield ['true', Type::true()];
        yield ['false', Type::false()];
        yield ['valueOfStrings', null];
        yield ['valueOfIntegers', null];
        yield ['valueOfIntEnum', null];
        yield ['valueOfStringEnum', null];
        yield ['valueOfNullableIntEnum', null];
        yield ['valueOfNullableStringEnum', null];
        yield ['keyOfStrings', null];
        yield ['keyOfIntegers', null];
        yield ['arrayKey', null];
        yield ['intMask', class_exists(IntMask::class) ? Type::int() : null];
        yield ['intMaskOf', class_exists(IntMaskOf::class) ? Type::int() : null];
        yield ['conditional', null];
        yield ['offsetAccess', null];
    }

    #[DataProvider('promotedPropertyProvider')]
    public function testExtractPromotedProperty(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(Php80Dummy::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function promotedPropertyProvider(): iterable
    {
        yield ['promoted', null];
        yield ['promotedAndMutated', Type::string()];
    }

    public function testSkipVoidNeverReturnTypeAccessors()
    {
        // Methods that return void or never should be skipped, so no types should be extracted
        $this->assertNull($this->extractor->getType(VoidNeverReturnTypeDummy::class, 'voidProperty'));
        $this->assertNull($this->extractor->getType(VoidNeverReturnTypeDummy::class, 'neverProperty'));
        // Normal getter should still work
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
        yield 'template of an abstract trait method' => [PhpDocInheritance\AfterUseDocBlock::class, 'items', null];
        yield 'parent method declared by a trait' => [PhpDocInheritance\ChildOfParentUsingDocumentedTrait::class, 'items', $items];

        yield 'class imported by the parent' => [PhpDocInheritance\Entity\ChildInEntity::class, 'thing', Type::object(PhpDocInheritance\Model\Sub\Thing::class)];
        yield 'class in the namespace of the parent' => [PhpDocInheritance\Entity\ChildInEntity::class, 'widget', Type::object(PhpDocInheritance\Model\Widget::class)];
        yield 'self' => [PhpDocInheritance\Entity\ChildInEntity::class, 'self', Type::object(PhpDocInheritance\Model\ParentInModel::class)];
        yield 'static' => [PhpDocInheritance\Entity\ChildInEntity::class, 'static', Type::object(PhpDocInheritance\Entity\ChildInEntity::class)];
        yield '$this' => [PhpDocInheritance\Entity\ChildInEntity::class, 'this', Type::object(PhpDocInheritance\Entity\ChildInEntity::class)];
        yield 'list of self' => [PhpDocInheritance\Entity\ChildInEntity::class, 'selfList', Type::list(Type::object(PhpDocInheritance\Model\ParentInModel::class))];
        yield 'parent' => [PhpDocInheritance\Entity\ChildInEntity::class, 'parentObject', Type::object(PhpDocInheritance\Model\GrandParentInModel::class)];
        yield 'type alias of the parent' => [PhpDocInheritance\Entity\ChildInEntity::class, 'aliased', null];
        yield 'parent method declared by a trait in another namespace' => [PhpDocInheritance\Entity\ChildInEntity::class, 'gadget', Type::object(PhpDocInheritance\Model\Sub\Gadget::class)];

        yield 'template bound by the class' => [PhpDocInheritance\BoundChild::class, 'items', null];
        yield 'template bound by an implementation' => [PhpDocInheritance\BoundImplementation::class, 'items', null];
        yield 'unbound template' => [PhpDocInheritance\UnboundChild::class, 'items', null];
        yield 'template bound by a parent for its trait' => [PhpDocInheritance\ChildOfParentUsingGenericTrait::class, 'items', null];
        yield 'template bound through a parent' => [PhpDocInheritance\BoundLeaf::class, 'items', null];

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

    public function testInheritedTypesKeepTheOwnDescriptions()
    {
        $this->assertSame('Returns the items.', $this->extractor->getShortDescription(PhpDocInheritance\ChildDummy::class, 'described'));
        $this->assertNull($this->extractor->getShortDescription(PhpDocInheritance\ChildDummy::class, 'items'));
        $this->assertNull($this->extractor->getDocBlock(PhpDocInheritance\ChildDummy::class, 'items'));
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
     */
    public function setOmittedType(array $omittedTagType)
    {
    }
}
