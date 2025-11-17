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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DockBlockFallback;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyCollection;
use Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php80Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\PseudoTypeDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\PseudoTypesDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsedInTrait;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsingTrait;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\NullableType;

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
        yield ['files', Type::union(Type::list(Type::object(\SplFileInfo::class)), Type::resource()), null, null];
        yield ['bal', Type::object(\DateTimeImmutable::class), 'A short description ignoring template.', "A long description...\n\n...over several lines."];
        yield ['parent', Type::object(ParentDummy::class), null, null];
        yield ['collection', Type::list(Type::object(\DateTimeImmutable::class)), null, null];
        yield ['nestedCollection', Type::list(Type::list(Type::string())), null, null];
        yield ['mixedCollection', Type::array(), null, null];
        yield ['nullableTypedCollection', Type::nullable(Type::list(Type::object(Dummy::class))), null, null];
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
        yield ['nullableCollectionOfNonNullableElements', Type::nullable(Type::list(Type::int())), null, null];
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
        yield ['files', Type::union(Type::list(Type::object(\SplFileInfo::class)), Type::resource())];
        yield ['bal', Type::object(\DateTimeImmutable::class)];
        yield ['parent', Type::object(ParentDummy::class)];
        yield ['collection', Type::list(Type::object(\DateTimeImmutable::class))];
        yield ['nestedCollection', Type::list(Type::list(Type::string()))];
        yield ['mixedCollection', Type::array()];
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
        yield ['nullableCollectionOfNonNullableElements', Type::nullable(Type::list(Type::int()))];
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
        yield ['files', Type::union(Type::list(Type::object(\SplFileInfo::class)), Type::resource())];
        yield ['bal', Type::object(\DateTimeImmutable::class)];
        yield ['parent', Type::object(ParentDummy::class)];
        yield ['collection', Type::list(Type::object(\DateTimeImmutable::class))];
        yield ['nestedCollection', Type::list(Type::list(Type::string()))];
        yield ['mixedCollection', Type::array()];
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
        yield ['nullableCollectionOfNonNullableElements', Type::nullable(Type::list(Type::int()))];
        yield ['nonNullableCollectionOfNullableElements', Type::list(Type::nullable(Type::int()))];
        yield ['nullableCollectionOfMultipleNonNullableElementTypes', Type::nullable(Type::list(Type::union(Type::int(), Type::string())))];
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

    public function testUnknownPseudoType()
    {
        $this->assertEquals(Type::object('scalar'), $this->extractor->getType(PseudoTypeDummy::class, 'unknownPseudoType'));
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
