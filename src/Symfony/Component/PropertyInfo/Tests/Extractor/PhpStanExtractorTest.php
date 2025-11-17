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
use PHPUnit\Framework\TestCase;
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
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyWithTemplateAndParent;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\DummyInDifferentNs;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor\DummyWithTemplateAndParentInDifferentNs;
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
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Type;

require_once __DIR__.'/../Fixtures/Extractor/DummyNamespace.php';

/**
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
class PhpStanExtractorTest extends TestCase
{
    private PhpStanExtractor $extractor;
    private PhpDocExtractor $phpDocExtractor;

    protected function setUp(): void
    {
        $this->extractor = new PhpStanExtractor();
        $this->phpDocExtractor = new PhpDocExtractor();
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
        yield ['files', Type::union(Type::list(Type::object(\SplFileInfo::class)), Type::resource())];
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
    }

    #[DataProvider('typesWithNoPrefixesProvider')]
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
        yield ['a', Type::int()];
        yield ['b', Type::nullable(Type::int())];
        yield ['c', Type::int()];
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

    #[DataProvider('allowPrivateAccessProvider')]
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
