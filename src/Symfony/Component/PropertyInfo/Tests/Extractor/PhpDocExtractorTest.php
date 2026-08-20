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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Clazz;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DockBlockFallback;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyCollection;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyGeneric;
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
use Symfony\Component\PropertyInfo\Tests\Fixtures\PseudoTypeDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\PseudoTypesDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\RejectedCandidateDocDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsedInTrait;
use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsingTrait;
use Symfony\Component\PropertyInfo\Tests\Fixtures\VoidNeverReturnTypeDummy;
use Symfony\Component\PropertyInfo\Type as LegacyType;
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

    public function testAdderWithSeveralRequiredParametersIsIgnored()
    {
        $this->assertNull($this->extractor->getType(MultiParameterAdderDocDummy::class, 'link'));
    }

    public function testRejectedCandidateMethodsAreIgnored()
    {
        $this->assertNull($this->extractor->getType(RejectedCandidateDocDummy::class, 'foo'));
        $this->assertNull($this->extractor->getType(RejectedCandidateDocDummy::class, 'bar'));
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('provideLegacyTypes')]
    public function testExtractLegacy($property, ?array $type, $shortDescription, $longDescription)
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getType()" instead.');

        $this->assertEquals($type, $this->extractor->getTypes(Dummy::class, $property));
        $this->assertSame($shortDescription, $this->extractor->getShortDescription(Dummy::class, $property));
        $this->assertSame($longDescription, $this->extractor->getLongDescription(Dummy::class, $property));
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

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testParamTagTypeIsOmittedLegacy()
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getType()" instead.');

        $this->assertNull($this->extractor->getTypes(OmittedParamTagTypeDocBlock::class, 'omittedType'));
    }

    public static function provideLegacyInvalidTypes()
    {
        return [
            'pub' => ['pub', null, null],
            'stat' => ['stat', null, null],
            'bar' => ['bar', 'Bar.', null],
        ];
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('provideLegacyInvalidTypes')]
    public function testInvalidLegacy($property, $shortDescription, $longDescription)
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getType()" instead.');

        $this->assertNull($this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy', $property));
        $this->assertSame($shortDescription, $this->extractor->getShortDescription('Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy', $property));
        $this->assertSame($longDescription, $this->extractor->getLongDescription('Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy', $property));
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testEmptyParamAnnotationLegacy()
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getType()" instead.');

        $this->assertNull($this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy', 'foo'));
        $this->assertSame('Foo.', $this->extractor->getShortDescription('Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy', 'foo'));
        $this->assertNull($this->extractor->getLongDescription('Symfony\Component\PropertyInfo\Tests\Fixtures\InvalidDummy', 'foo'));
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('provideLegacyTypesWithNoPrefixes')]
    public function testExtractTypesWithNoPrefixesLegacy($property, ?array $type = null)
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getType()" instead.');

        $noPrefixExtractor = new PhpDocExtractor(null, [], [], []);

        $this->assertEquals($type, $noPrefixExtractor->getTypes(Dummy::class, $property));
    }

    public static function provideLegacyTypes()
    {
        return [
            ['foo', null, 'Short description.', 'Long description.'],
            ['bar', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING)], 'This is bar', null],
            ['baz', [new LegacyType(LegacyType::BUILTIN_TYPE_INT)], 'Should be used.', null],
            ['foo2', [new LegacyType(LegacyType::BUILTIN_TYPE_FLOAT)], null, null],
            ['foo3', [new LegacyType(LegacyType::BUILTIN_TYPE_CALLABLE)], null, null],
            ['foo4', [new LegacyType(LegacyType::BUILTIN_TYPE_NULL)], null, null],
            ['foo5', null, null, null],
            [
                'files',
                [
                    new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'SplFileInfo')),
                    new LegacyType(LegacyType::BUILTIN_TYPE_RESOURCE),
                ],
                null,
                null,
            ],
            ['bal', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable')], 'A short description ignoring template.', "A long description...\n\n...over several lines."],
            ['parent', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')], null, null],
            ['collection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable'))], null, null],
            ['nestedCollection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_STRING, false)))], null, null],
            ['mixedCollection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, null, null)], null, null],
            ['a', [new LegacyType(LegacyType::BUILTIN_TYPE_INT)], 'A.', null],
            ['b', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, true, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')], 'B.', null],
            ['c', [new LegacyType(LegacyType::BUILTIN_TYPE_BOOL, true)], null, null],
            ['ct', [new LegacyType(LegacyType::BUILTIN_TYPE_TRUE, true)], null, null],
            ['cf', [new LegacyType(LegacyType::BUILTIN_TYPE_FALSE, true)], null, null],
            ['d', [new LegacyType(LegacyType::BUILTIN_TYPE_BOOL)], null, null],
            ['dt', [new LegacyType(LegacyType::BUILTIN_TYPE_TRUE)], null, null],
            ['df', [new LegacyType(LegacyType::BUILTIN_TYPE_FALSE)], null, null],
            ['e', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_RESOURCE))], null, null],
            ['f', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable'))], null, null],
            ['g', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, true, null, true)], 'Nullable array.', null],
            ['h', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, true)], null, null],
            ['i', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, true), new LegacyType(LegacyType::BUILTIN_TYPE_INT, true)], null, null],
            ['j', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, true, 'DateTimeImmutable')], null, null],
            ['nullableCollectionOfNonNullableElements', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, true, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_INT, false))], null, null],
            ['donotexist', null, null, null],
            ['staticGetter', null, null, null],
            ['staticSetter', null, null, null],
            ['emptyVar', null, 'This should not be removed.', null],
            ['arrayWithKeys', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_STRING), new LegacyType(LegacyType::BUILTIN_TYPE_STRING))], null, null],
            ['arrayOfMixed', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_STRING), null)], null, null],
            ['listOfStrings', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_STRING))], null, null],
            ['self', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Dummy::class)], null, null],
            ['collectionAsObject', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, DummyCollection::class, true, [new LegacyType(LegacyType::BUILTIN_TYPE_INT)], [new LegacyType(LegacyType::BUILTIN_TYPE_STRING)])], null, null],
            ['nullableTypedCollection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, true, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Dummy::class))], null, null],
            ['unionWithMixed', null, null, null],
        ];
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('provideLegacyCollectionTypes')]
    public function testExtractCollectionLegacy($property, ?array $type, $shortDescription, $longDescription)
    {
        $this->testExtractLegacy($property, $type, $shortDescription, $longDescription);
    }

    public static function provideLegacyCollectionTypes()
    {
        return [
            ['iteratorCollection', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'Iterator', true, [new LegacyType(LegacyType::BUILTIN_TYPE_STRING), new LegacyType(LegacyType::BUILTIN_TYPE_INT)], new LegacyType(LegacyType::BUILTIN_TYPE_STRING))], null, null],
            ['iteratorCollectionWithKey', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'Iterator', true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_STRING))], null, null],
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
                null,
                null,
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
                null,
                null,
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
                null,
                null,
            ],
        ];
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('provideLegacyTypesWithCustomPrefixes')]
    public function testExtractTypesWithCustomPrefixesLegacy($property, ?array $type = null)
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getType()" instead.');

        $customExtractor = new PhpDocExtractor(null, ['add', 'remove'], ['is', 'can']);

        $this->assertEquals($type, $customExtractor->getTypes(Dummy::class, $property));
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
                    new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'SplFileInfo')),
                    new LegacyType(LegacyType::BUILTIN_TYPE_RESOURCE),
                ],
            ],
            ['bal', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable')]],
            ['parent', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')]],
            ['collection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable'))]],
            ['nestedCollection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_STRING, false)))]],
            ['mixedCollection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, null, null)]],
            ['a', null],
            ['b', null],
            ['c', [new LegacyType(LegacyType::BUILTIN_TYPE_BOOL, true)]],
            ['d', [new LegacyType(LegacyType::BUILTIN_TYPE_BOOL)]],
            ['e', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_RESOURCE))]],
            ['f', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable'))]],
            ['g', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, true, null, true)]],
            ['h', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, true)]],
            ['i', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, true), new LegacyType(LegacyType::BUILTIN_TYPE_INT, true)]],
            ['j', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, true, 'DateTimeImmutable')]],
            ['nullableCollectionOfNonNullableElements', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, true, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_INT, false))]],
            ['nonNullableCollectionOfNullableElements', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_INT, true))]],
            ['nullableCollectionOfMultipleNonNullableElementTypes', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, true, null, true, null, [new LegacyType(LegacyType::BUILTIN_TYPE_INT), new LegacyType(LegacyType::BUILTIN_TYPE_STRING)])]],
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
                    new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'SplFileInfo')),
                    new LegacyType(LegacyType::BUILTIN_TYPE_RESOURCE),
                ],
            ],
            ['bal', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable')]],
            ['parent', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy')]],
            ['collection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeImmutable'))]],
            ['nestedCollection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_STRING, false)))]],
            ['mixedCollection', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, null, null)]],
            ['a', null],
            ['b', null],
            ['c', null],
            ['d', null],
            ['e', null],
            ['f', null],
            ['g', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, true, null, true)]],
            ['h', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, true)]],
            ['i', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING, true), new LegacyType(LegacyType::BUILTIN_TYPE_INT, true)]],
            ['j', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, true, 'DateTimeImmutable')]],
            ['nullableCollectionOfNonNullableElements', [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, true, null, true, null, new LegacyType(LegacyType::BUILTIN_TYPE_INT, false))]],
            ['donotexist', null],
            ['staticGetter', null],
            ['staticSetter', null],
        ];
    }

    public function testReturnNullOnEmptyDocBlock()
    {
        $this->assertNull($this->extractor->getShortDescription(EmptyDocBlock::class, 'foo'));
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

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('provideLegacyDockBlockFallbackTypes')]
    public function testDocBlockFallbackLegacy($property, $types)
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getType()" instead.');

        $this->assertEquals($types, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\DockBlockFallback', $property));
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('provideLegacyPropertiesDefinedByTraits')]
    public function testPropertiesDefinedByTraitsLegacy(string $property, LegacyType $type)
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getType()" instead.');

        $this->assertEquals([$type], $this->extractor->getTypes(DummyUsingTrait::class, $property));
    }

    public static function provideLegacyPropertiesDefinedByTraits(): array
    {
        return [
            ['propertyInTraitPrimitiveType', new LegacyType(LegacyType::BUILTIN_TYPE_STRING)],
            ['propertyInTraitObjectSameNamespace', new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, DummyUsedInTrait::class)],
            ['propertyInTraitObjectDifferentNamespace', new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Dummy::class)],
            ['propertyInExternalTraitPrimitiveType', new LegacyType(LegacyType::BUILTIN_TYPE_STRING)],
            ['propertyInExternalTraitObjectSameNamespace', new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Dummy::class)],
            ['propertyInExternalTraitObjectDifferentNamespace', new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, DummyUsedInTrait::class)],
        ];
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('provideLegacyMethodsDefinedByTraits')]
    public function testMethodsDefinedByTraitsLegacy(string $property, LegacyType $type)
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getType()" instead.');

        $this->assertEquals([$type], $this->extractor->getTypes(DummyUsingTrait::class, $property));
    }

    public static function provideLegacyMethodsDefinedByTraits(): array
    {
        return [
            ['methodInTraitPrimitiveType', new LegacyType(LegacyType::BUILTIN_TYPE_STRING)],
            ['methodInTraitObjectSameNamespace', new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, DummyUsedInTrait::class)],
            ['methodInTraitObjectDifferentNamespace', new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Dummy::class)],
            ['methodInExternalTraitPrimitiveType', new LegacyType(LegacyType::BUILTIN_TYPE_STRING)],
            ['methodInExternalTraitObjectSameNamespace', new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Dummy::class)],
            ['methodInExternalTraitObjectDifferentNamespace', new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, DummyUsedInTrait::class)],
        ];
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('provideLegacyPropertiesStaticType')]
    public function testPropertiesStaticTypeLegacy(string $class, string $property, LegacyType $type)
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getType()" instead.');

        $this->assertEquals([$type], $this->extractor->getTypes($class, $property));
    }

    public static function provideLegacyPropertiesStaticType(): array
    {
        return [
            [ParentDummy::class, 'propertyTypeStatic', new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, ParentDummy::class)],
            [Dummy::class, 'propertyTypeStatic', new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Dummy::class)],
        ];
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('provideLegacyPropertiesParentType')]
    public function testPropertiesParentTypeLegacy(string $class, string $property, ?array $types)
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getType()" instead.');

        $this->assertEquals($types, $this->extractor->getTypes($class, $property));
    }

    public static function provideLegacyPropertiesParentType(): array
    {
        return [
            [ParentDummy::class, 'parentAnnotationNoParent', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'parent')]],
            [Dummy::class, 'parentAnnotation', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, ParentDummy::class)]],
        ];
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testUnknownPseudoTypeLegacy()
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getType()" instead.');

        $this->assertEquals([
            new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'Symfony\\Component\\PropertyInfo\\Tests\\Fixtures\\unknownpseudo'),
        ], $this->extractor->getTypes(PseudoTypeDummy::class, 'unknownPseudoType'));
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testScalarPseudoTypeLegacy()
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getType()" instead.');

        $this->assertEquals([
            new LegacyType(LegacyType::BUILTIN_TYPE_BOOL),
            new LegacyType(LegacyType::BUILTIN_TYPE_FLOAT),
            new LegacyType(LegacyType::BUILTIN_TYPE_INT),
            new LegacyType(LegacyType::BUILTIN_TYPE_STRING),
        ], $this->extractor->getTypes(PseudoTypeDummy::class, 'scalarPseudoType'));
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('legacyGenericsProvider')]
    public function testGenericsLegacy(string $class, string $property, array $expectedTypes)
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getType()" instead.');

        $this->assertEquals($expectedTypes, $this->extractor->getTypes($class, $property));
    }

    /**
     * @return iterable<array{0: class-string, 1: string, 2: list<LegacyType>}>
     */
    public static function legacyGenericsProvider(): iterable
    {
        yield [
            Dummy::class,
            'genericInterface',
            [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'BackedEnum', false, [new LegacyType(LegacyType::BUILTIN_TYPE_STRING), new LegacyType(LegacyType::BUILTIN_TYPE_INT)], [new LegacyType(LegacyType::BUILTIN_TYPE_STRING)])],
        ];
        yield [
            DummyGeneric::class,
            'basicClass',
            [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Clazz::class, false, [new LegacyType(LegacyType::BUILTIN_TYPE_STRING), new LegacyType(LegacyType::BUILTIN_TYPE_INT)], [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Dummy::class)])],
        ];
        yield [
            DummyGeneric::class,
            'basicInterface',
            [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, IFace::class, false, [new LegacyType(LegacyType::BUILTIN_TYPE_STRING), new LegacyType(LegacyType::BUILTIN_TYPE_INT)], [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Dummy::class)])],
        ];
        yield [
            DummyGeneric::class,
            'twoGenerics',
            [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Clazz::class, false, [new LegacyType(LegacyType::BUILTIN_TYPE_INT)], [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Dummy::class)])],
        ];
        yield [
            DummyGeneric::class,
            'nullableClass',
            [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, true, Clazz::class, false, [new LegacyType(LegacyType::BUILTIN_TYPE_STRING), new LegacyType(LegacyType::BUILTIN_TYPE_INT)], [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Dummy::class)])],
        ];
        yield [
            DummyGeneric::class,
            'nullableInterface',
            [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, true, IFace::class, false, [new LegacyType(LegacyType::BUILTIN_TYPE_STRING), new LegacyType(LegacyType::BUILTIN_TYPE_INT)], [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Dummy::class)])],
        ];

        // phpdocumentor/reflection-docblock >= 6
        if (class_exists(Generic::class)) {
            yield [
                DummyGeneric::class,
                'threeGenerics',
                [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Clazz::class, false, [new LegacyType(LegacyType::BUILTIN_TYPE_INT)], [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, Dummy::class)])],
            ];
        }
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

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('provideLegacyConstructorTypes')]
    public function testExtractConstructorTypesLegacy($property, ?array $type = null)
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypesFromConstructor()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypeFromConstructor()" instead.');

        $this->assertEquals($type, $this->extractor->getTypesFromConstructor('Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummy', $property));
    }

    public static function provideLegacyConstructorTypes()
    {
        return [
            ['date', [new LegacyType(LegacyType::BUILTIN_TYPE_INT)]],
            ['timezone', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeZone')]],
            ['dateObject', [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, 'DateTimeInterface')]],
            ['dateTime', null],
            ['ddd', null],
            ['mixed', null],
        ];
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('provideLegacyPseudoTypes')]
    public function testPseudoTypesLegacy($property, array $type)
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getType()" instead.');

        $this->assertEquals($type, $this->extractor->getTypes('Symfony\Component\PropertyInfo\Tests\Fixtures\PseudoTypesDummy', $property));
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
            ['positiveInt', [new LegacyType(LegacyType::BUILTIN_TYPE_INT, false, null)]],
        ];
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('provideLegacyPromotedProperty')]
    public function testExtractPromotedPropertyLegacy(string $property, ?array $types)
    {
        $this->expectUserDeprecationMessage('Since symfony/property-info 7.3: The "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getTypes()" method is deprecated, use "Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor::getType()" instead.');

        $this->assertEquals($types, $this->extractor->getTypes(Php80Dummy::class, $property));
    }

    public static function provideLegacyPromotedProperty(): array
    {
        return [
            ['promoted', null],
            ['promotedAndMutated', [new LegacyType(LegacyType::BUILTIN_TYPE_STRING)]],
        ];
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

        // BC layer for type-info < 7.2
        if (!class_exists(NullableType::class)) {
            yield ['i', Type::union(Type::int(), Type::string(), Type::null()), null, null];
        } else {
            yield ['i', Type::nullable(Type::union(Type::int(), Type::string())), null, null];
        }

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

        // BC layer for type-info < 7.2
        if (!class_exists(NullableType::class)) {
            yield ['i', Type::union(Type::int(), Type::string(), Type::null())];
        } else {
            yield ['i', Type::nullable(Type::union(Type::int(), Type::string()))];
        }

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

        // BC layer for type-info < 7.2
        if (!class_exists(NullableType::class)) {
            yield ['i', Type::union(Type::int(), Type::string(), Type::null())];
        } else {
            yield ['i', Type::nullable(Type::union(Type::int(), Type::string()))];
        }

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

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('inheritedPromotedPropertyWithConstructorOverrideProviderLegacy')]
    public function testInheritedPromotedPropertyWithConstructorOverrideLegacy(string $class, string $property, ?array $expectedTypes)
    {
        $this->assertEquals($expectedTypes, $this->extractor->getTypes($class, $property));
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: ?array}>
     */
    public static function inheritedPromotedPropertyWithConstructorOverrideProviderLegacy(): iterable
    {
        $expectedItemsType = [new LegacyType(
            LegacyType::BUILTIN_TYPE_ARRAY,
            false,
            null,
            true,
            new LegacyType(LegacyType::BUILTIN_TYPE_STRING),
            new LegacyType(LegacyType::BUILTIN_TYPE_INT)
        )];

        yield 'parent promoted property' => [ParentWithPromotedPropertyDocBlock::class, 'items', $expectedItemsType];
        yield 'child without constructor override' => [ChildWithoutConstructorOverride::class, 'items', $expectedItemsType];
        yield 'child with constructor override' => [ChildWithConstructorOverride::class, 'items', $expectedItemsType];
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

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testSkipVoidNeverReturnTypeAccessorsLegacy()
    {
        // Methods that return void or never should be skipped, so no types should be extracted
        $this->assertNull($this->extractor->getTypes(VoidNeverReturnTypeDummy::class, 'voidProperty'));
        $this->assertNull($this->extractor->getTypes(VoidNeverReturnTypeDummy::class, 'neverProperty'));
        // Normal getter should still work
        $types = $this->extractor->getTypes(VoidNeverReturnTypeDummy::class, 'normalProperty');
        $this->assertNotNull($types);
        $this->assertCount(1, $types);
        $this->assertEquals(LegacyType::BUILTIN_TYPE_STRING, $types[0]->getBuiltinType());
    }

    /**
     * @param class-string $class
     * @param class-string $expectedResolvedClass
     */
    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('selfDocBlockResolutionProviderLegacy')]
    public function testSelfDocBlockResolvesToDeclaringClassLegacy(string $class, string $property, string $expectedResolvedClass)
    {
        $this->assertEquals([new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, $expectedResolvedClass)], $this->extractor->getTypes($class, $property));
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string, 2: class-string}>
     */
    public static function selfDocBlockResolutionProviderLegacy(): iterable
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
