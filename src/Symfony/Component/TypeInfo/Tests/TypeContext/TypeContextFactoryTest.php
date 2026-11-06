<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\TypeContext;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Tests\Fixtures\AbstractDummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\AnotherNamespace\DummyInDifferentNs;
use Symfony\Component\TypeInfo\Tests\Fixtures\AnotherNamespace\DummyWithTemplateAndParentInDifferentNs;
use Symfony\Component\TypeInfo\Tests\Fixtures\Dummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyImportingUnresolvableTypeAlias;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyReimportingUnresolvableTypeAlias;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithCovariantTemplates;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithImportedOnlyTypeAliases;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithInvalidTypeAlias;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithInvalidTypeAliasImport;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithMixedTemplates;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithMutuallyImportedTypeAliasesA;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithPhpDoc;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithPhpstanCovariantTemplates;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithPhpstanTemplates;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithPsalmCovariantTemplates;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithPsalmTemplates;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithRecursiveTypeAliases;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithTemplateAndParent;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithTemplates;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithTemplateTypeAlias;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithTypeAliases;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithTypeAliasImportedFromInvalidClassName;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithUnresolvableTypeAlias;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithUses;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithUsesWindowsLineEndings;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\AbstractHolder;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\ChildEnvelope;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\DummyEnvelope;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\DummyGrandChildEnvelope;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\DummyHolder;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\DummyItemUser;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\DummyUntaggedHolder;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\Envelope;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\GrandChildEnvelope;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\HasItem;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\Holder;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\IntListHolder;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\IntListItemUser;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\IntSameNameEnvelope;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\ListEnvelope;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\ListItemUser;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\MissingTypePair;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\NestedPair;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\Pair;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\PhpStanPrefixedEnvelope;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\SameNameEnvelope;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\SwappedPair;
use Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates\TooManyTypesEnvelope;
use Symfony\Component\TypeInfo\Tests\Fixtures\ParentDummyWithTemplate;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;

require_once __DIR__.'/../Fixtures/InheritedTemplates.php';

class TypeContextFactoryTest extends TestCase
{
    private TypeContextFactory $typeContextFactory;

    protected function setUp(): void
    {
        $this->typeContextFactory = new TypeContextFactory(new StringTypeResolver());
    }

    public function testCollectClassNames()
    {
        $typeContext = $this->typeContextFactory->createFromClassName(Dummy::class, AbstractDummy::class);
        $this->assertSame(Dummy::class, $typeContext->calledClassName);
        $this->assertSame(AbstractDummy::class, $typeContext->declaringClassName);

        $typeContext = $this->typeContextFactory->createFromReflection(new \ReflectionClass(Dummy::class));
        $this->assertSame(Dummy::class, $typeContext->calledClassName);
        $this->assertSame(Dummy::class, $typeContext->declaringClassName);

        $typeContext = $this->typeContextFactory->createFromReflection(new \ReflectionProperty(Dummy::class, 'id'));
        $this->assertSame(Dummy::class, $typeContext->calledClassName);
        $this->assertSame(Dummy::class, $typeContext->declaringClassName);

        $typeContext = $this->typeContextFactory->createFromReflection(new \ReflectionMethod(Dummy::class, 'getId'));
        $this->assertSame(Dummy::class, $typeContext->calledClassName);
        $this->assertSame(Dummy::class, $typeContext->declaringClassName);

        $typeContext = $this->typeContextFactory->createFromReflection(new \ReflectionParameter([Dummy::class, 'setId'], 'id'));
        $this->assertSame(Dummy::class, $typeContext->calledClassName);
        $this->assertSame(Dummy::class, $typeContext->declaringClassName);
    }

    public function testCacheResultWhenToStringTypeResolver()
    {
        $typeContext = $this->typeContextFactory->createFromClassName(Dummy::class, AbstractDummy::class);
        $cachedtypeContext = $this->typeContextFactory->createFromClassName(Dummy::class, AbstractDummy::class);
        $this->assertSame($typeContext, $cachedtypeContext);
    }

    public function testCollectNamespace()
    {
        $namespace = 'Symfony\\Component\\TypeInfo\\Tests\\Fixtures';

        $this->assertSame($namespace, $this->typeContextFactory->createFromClassName(Dummy::class)->namespace);

        $this->assertEquals($namespace, $this->typeContextFactory->createFromReflection(new \ReflectionClass(Dummy::class))->namespace);
        $this->assertEquals($namespace, $this->typeContextFactory->createFromReflection(new \ReflectionProperty(Dummy::class, 'id'))->namespace);
        $this->assertEquals($namespace, $this->typeContextFactory->createFromReflection(new \ReflectionMethod(Dummy::class, 'getId'))->namespace);
        $this->assertEquals($namespace, $this->typeContextFactory->createFromReflection(new \ReflectionParameter([Dummy::class, 'setId'], 'id'))->namespace);
    }

    public function testCollectUses()
    {
        $this->assertSame([], $this->typeContextFactory->createFromClassName(Dummy::class)->uses);

        $uses = [
            'Type' => Type::class,
            'TI' => 'Symfony\\Component\\TypeInfo\\TypeIdentifier',
            \DateTimeInterface::class => \DateTimeInterface::class,
            'DateTime' => \DateTimeImmutable::class,
        ];

        $this->assertSame($uses, $this->typeContextFactory->createFromClassName(DummyWithUses::class)->uses);

        $this->assertEquals($uses, $this->typeContextFactory->createFromReflection(new \ReflectionClass(DummyWithUses::class))->uses);
        $this->assertEquals($uses, $this->typeContextFactory->createFromReflection(new \ReflectionProperty(DummyWithUses::class, 'createdAt'))->uses);
        $this->assertEquals($uses, $this->typeContextFactory->createFromReflection(new \ReflectionMethod(DummyWithUses::class, 'setCreatedAt'))->uses);
        $this->assertEquals($uses, $this->typeContextFactory->createFromReflection(new \ReflectionParameter([DummyWithUses::class, 'setCreatedAt'], 'createdAt'))->uses);
    }

    public function testCollectUsesWindowsLineEndings()
    {
        self::assertSame(\count(file(__DIR__.'/../Fixtures/DummyWithUsesWindowsLineEndings.php')), substr_count(file_get_contents(__DIR__.'/../Fixtures/DummyWithUsesWindowsLineEndings.php'), "\r\n"));

        $uses = [
            'Type' => Type::class,
            \DateTimeInterface::class => \DateTimeInterface::class,
            'DateTime' => \DateTimeImmutable::class,
        ];

        $this->assertSame($uses, $this->typeContextFactory->createFromClassName(DummyWithUsesWindowsLineEndings::class)->uses);

        $this->assertEquals($uses, $this->typeContextFactory->createFromReflection(new \ReflectionClass(DummyWithUsesWindowsLineEndings::class))->uses);
        $this->assertEquals($uses, $this->typeContextFactory->createFromReflection(new \ReflectionProperty(DummyWithUsesWindowsLineEndings::class, 'createdAt'))->uses);
        $this->assertEquals($uses, $this->typeContextFactory->createFromReflection(new \ReflectionMethod(DummyWithUsesWindowsLineEndings::class, 'setCreatedAt'))->uses);
        $this->assertEquals($uses, $this->typeContextFactory->createFromReflection(new \ReflectionParameter([DummyWithUsesWindowsLineEndings::class, 'setCreatedAt'], 'createdAt'))->uses);
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('collectTemplatesDataProvider')]
    public function testCollectTemplates(string $className)
    {
        $this->assertEquals([
            'T' => Type::union(Type::int(), Type::string()),
            'U' => Type::mixed(),
        ], $this->typeContextFactory->createFromClassName($className)->templates);

        $this->assertEquals([
            'T' => Type::union(Type::int(), Type::string()),
            'U' => Type::mixed(),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionClass($className))->templates);

        $this->assertEquals([
            'T' => Type::union(Type::int(), Type::string()),
            'U' => Type::mixed(),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionProperty($className, 'price'))->templates);

        $this->assertEquals([
            'T' => Type::union(Type::int(), Type::float()),
            'U' => Type::mixed(),
            'V' => Type::mixed(),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionMethod($className, 'getPrice'))->templates);

        $this->assertEquals([
            'T' => Type::union(Type::int(), Type::float()),
            'U' => Type::mixed(),
            'V' => Type::mixed(),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionParameter([$className, 'getPrice'], 'inCents'))->templates);
    }

    /**
     * @return iterable<array{0: class-string}>
     */
    public static function collectTemplatesDataProvider(): iterable
    {
        yield [DummyWithTemplates::class];
        yield [DummyWithPhpstanTemplates::class];
        yield [DummyWithPsalmTemplates::class];
        yield [DummyWithCovariantTemplates::class];
        yield [DummyWithPsalmCovariantTemplates::class];
        yield [DummyWithPhpstanCovariantTemplates::class];
    }

    public function testCollectTemplatesInDeclarationOrder()
    {
        $this->assertSame(['S', 'T', 'U', 'V', 'W', 'X', 'Y'], array_keys($this->typeContextFactory->createFromClassName(DummyWithMixedTemplates::class)->templates));
    }

    public function testCollectTemplatesWithParent()
    {
        $this->assertEquals([
            'T' => Type::object(DummyInDifferentNs::class),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionClass(DummyWithTemplateAndParent::class))->templates);

        $this->assertEquals([
            'T' => Type::object(DummyInDifferentNs::class),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionClass(DummyWithTemplateAndParentInDifferentNs::class))->templates);
    }

    public function testCollectTemplatesOfCalledClassAndTypeAliasesOfDeclaringClass()
    {
        $typeContext = $this->typeContextFactory->createFromClassName(DummyWithTemplateAndParent::class, ParentDummyWithTemplate::class);

        $this->assertEquals(['T' => Type::object(DummyInDifferentNs::class)], $typeContext->templates);
        $this->assertEquals(['ParentInt' => Type::int()], $typeContext->typeAliases);
    }

    /**
     * @param array<string, Type> $expectedTypes
     * @param class-string        $calledClassName
     * @param class-string        $declaringClassName
     */
    #[DataProvider('collectInheritedTemplateTypesDataProvider')]
    public function testCollectInheritedTemplateTypes(array $expectedTypes, string $calledClassName, string $declaringClassName)
    {
        $this->assertEquals($expectedTypes, $this->typeContextFactory->createFromClassName($calledClassName, $declaringClassName)->inheritedTemplateTypes);
    }

    /**
     * @return iterable<array{0: array<string, Type>, 1: class-string, 2: class-string}>
     */
    public static function collectInheritedTemplateTypesDataProvider(): iterable
    {
        yield 'concrete type' => [['T' => Type::object(Dummy::class)], DummyEnvelope::class, Envelope::class];
        yield 'renamed template' => [['T' => Type::template('U', Type::object(Dummy::class))], ChildEnvelope::class, Envelope::class];
        yield 'two levels' => [['T' => Type::template('V', Type::object(Dummy::class))], GrandChildEnvelope::class, Envelope::class];
        yield 'three levels' => [['T' => Type::object(Dummy::class)], DummyGrandChildEnvelope::class, Envelope::class];
        yield 'intermediate class' => [['T' => Type::int(), 'U' => Type::object(Dummy::class)], DummyGrandChildEnvelope::class, ChildEnvelope::class];
        yield 'nested template' => [['T' => Type::list(Type::template('V'))], ListEnvelope::class, Envelope::class];
        yield 'nested generic' => [['K' => Type::int(), 'V' => Type::generic(Type::object(Pair::class), Type::template('T'), Type::string())], NestedPair::class, Pair::class];
        yield 'swapped templates' => [['K' => Type::template('B'), 'V' => Type::template('A')], SwappedPair::class, Pair::class];
        yield 'no tag' => [[], SameNameEnvelope::class, Envelope::class];
        yield 'same name without tag' => [['T' => Type::int()], IntSameNameEnvelope::class, Envelope::class];
        yield 'too many types' => [['T' => Type::int()], TooManyTypesEnvelope::class, Envelope::class];
        yield 'missing type' => [['K' => Type::int()], MissingTypePair::class, Pair::class];
        yield 'phpstan prefix' => [['T' => Type::string()], PhpStanPrefixedEnvelope::class, Envelope::class];
        yield 'interface' => [['T' => Type::template('U')], AbstractHolder::class, Holder::class];
        yield 'interface of parent' => [['T' => Type::object(Dummy::class)], DummyHolder::class, Holder::class];
        yield 'interface without tag' => [['T' => Type::object(Dummy::class)], DummyUntaggedHolder::class, Holder::class];
        yield 'parent interface' => [['T' => Type::list(Type::int())], IntListHolder::class, Holder::class];
        yield 'trait' => [['T' => Type::object(Dummy::class)], DummyItemUser::class, HasItem::class];
        yield 'nested template of trait' => [['T' => Type::list(Type::template('U'))], ListItemUser::class, HasItem::class];
        yield 'trait of parent' => [['T' => Type::list(Type::int())], IntListItemUser::class, HasItem::class];
        yield 'declaring class without template' => [[], Dummy::class, AbstractDummy::class];
    }

    public function testResolveInheritedTemplateTypes()
    {
        $stringTypeResolver = new StringTypeResolver();
        $typeContextFactory = new TypeContextFactory($stringTypeResolver);

        $this->assertEquals(Type::object(Dummy::class), $stringTypeResolver->resolve('T', $typeContextFactory->createFromClassName(DummyGrandChildEnvelope::class, Envelope::class)));
        $this->assertEquals(Type::list(Type::int()), $stringTypeResolver->resolve('T', $typeContextFactory->createFromClassName(IntListItemUser::class, HasItem::class)));
        $this->assertEquals(Type::template('T'), $stringTypeResolver->resolve('T', $typeContextFactory->createFromClassName(SameNameEnvelope::class, Envelope::class)));
    }

    public function testDoNotCollectTemplatesWhenToStringTypeResolver()
    {
        $typeContextFactory = new TypeContextFactory();

        $this->assertEquals([], $typeContextFactory->createFromClassName(DummyWithTemplates::class)->templates);
    }

    /**
     * @param array<string, Type> $expectedTypeAliases
     * @param class-string        $className
     */
    #[DataProvider('collectTypeAliasesDataProvider')]
    public function testCollectTypeAliases(array $expectedTypeAliases, string $className)
    {
        $this->assertEquals($expectedTypeAliases, $this->typeContextFactory->createFromClassName($className)->typeAliases);
    }

    /**
     * @return iterable<array{0: array<string, Type>, 1: class-string}>
     */
    public static function collectTypeAliasesDataProvider(): iterable
    {
        yield [[
            'CustomString' => Type::string(),
            'CustomInt' => Type::int(),
            'CustomArray' => Type::arrayShape([0 => Type::int(), 1 => Type::string(), 2 => Type::bool()]),
            'AliasedCustomInt' => Type::int(),
            'PsalmCustomString' => Type::string(),
            'PsalmCustomInt' => Type::int(),
            'PsalmCustomArray' => Type::arrayShape([0 => Type::int(), 1 => Type::string(), 2 => Type::bool()]),
            'PsalmAliasedCustomInt' => Type::int(),
        ], DummyWithTypeAliases::class];

        yield [[
            'CustomString' => Type::string(),
            'CustomInt' => Type::int(),
            'CustomArray' => Type::arrayShape([0 => Type::int(), 1 => Type::string(), 2 => Type::bool()]),
            'AliasedCustomInt' => Type::int(),
            'PsalmCustomString' => Type::string(),
            'PsalmCustomInt' => Type::int(),
            'PsalmCustomArray' => Type::arrayShape([0 => Type::int(), 1 => Type::string(), 2 => Type::bool()]),
            'PsalmAliasedCustomInt' => Type::int(),
        ], DummyWithTypeAliases::class];

        yield [[
            'CustomString' => Type::string(),
            'CustomInt' => Type::int(),
            'CustomArray' => Type::arrayShape([0 => Type::int(), 1 => Type::string(), 2 => Type::bool()]),
            'AliasedCustomInt' => Type::int(),
            'PsalmCustomString' => Type::string(),
            'PsalmCustomInt' => Type::int(),
            'PsalmCustomArray' => Type::arrayShape([0 => Type::int(), 1 => Type::string(), 2 => Type::bool()]),
            'PsalmAliasedCustomInt' => Type::int(),
        ], DummyWithTypeAliases::class];

        yield [['CustomInt' => Type::int()], DummyWithImportedOnlyTypeAliases::class];
        yield [['AliasWithTemplate' => Type::template('T')], DummyWithTemplateTypeAlias::class];

        // type aliases that cannot be resolved are left out
        yield [['Name' => Type::string()], DummyWithUnresolvableTypeAlias::class];
        yield [['Name' => Type::string()], DummyImportingUnresolvableTypeAlias::class];
        yield [['Name' => Type::string()], DummyReimportingUnresolvableTypeAlias::class];
    }

    public function testCollectTypeAliasesOfClassFromReflection()
    {
        $typeAliases = ['CustomInt' => Type::int(), 'PsalmCustomInt' => Type::int(), 'QuotedKeysArray' => Type::arrayShape(["it's" => Type::int(), 'quoted key' => Type::int()])];

        $this->assertEquals($typeAliases, $this->typeContextFactory->createFromReflection(new \ReflectionClass(DummyWithPhpDoc::class))->typeAliases);
        $this->assertEquals($typeAliases, $this->typeContextFactory->createFromReflection(new \ReflectionProperty(DummyWithPhpDoc::class, 'aliasedInt'))->typeAliases);
        $this->assertEquals($typeAliases, $this->typeContextFactory->createFromReflection(new \ReflectionMethod(DummyWithPhpDoc::class, 'getNextDummy'))->typeAliases);
        $this->assertEquals($typeAliases, $this->typeContextFactory->createFromReflection(new \ReflectionParameter([DummyWithPhpDoc::class, 'getNextDummy'], 'dummy'))->typeAliases);
    }

    public function testCollectTypeAliasesWithExtraTypeAliases()
    {
        $typeContextFactory = new TypeContextFactory(new StringTypeResolver(), [
            'Custom' => Type::int(),
            'CustomArray' => Type::list(Type::bool()), // will be overridden by the resolved type alias
        ]);

        $this->assertEquals([
            'Custom' => Type::int(),
            'CustomArray' => Type::list(Type::bool()),
        ], $typeContextFactory->createFromClassName(Dummy::class)->typeAliases);

        $this->assertEquals([
            'Custom' => Type::int(),
            'CustomString' => Type::string(),
            'CustomInt' => Type::int(),
            'CustomArray' => Type::arrayShape([0 => Type::int(), 1 => Type::string(), 2 => Type::bool()]),
            'AliasedCustomInt' => Type::int(),
            'PsalmCustomString' => Type::string(),
            'PsalmCustomInt' => Type::int(),
            'PsalmCustomArray' => Type::arrayShape([0 => Type::int(), 1 => Type::string(), 2 => Type::bool()]),
            'PsalmAliasedCustomInt' => Type::int(),
        ], $typeContextFactory->createFromClassName(DummyWithTypeAliases::class)->typeAliases);
    }

    public function testDoNotCollectTypeAliasesWhenToStringTypeResolver()
    {
        $typeContextFactory = new TypeContextFactory();

        $this->assertEquals([], $typeContextFactory->createFromClassName(DummyWithTypeAliases::class)->typeAliases);
    }

    public function testThrowWhenImportingInvalidAlias()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('Cannot find any "Invalid" type alias in "%s".', DummyWithTypeAliases::class));

        $this->typeContextFactory->createFromClassName(DummyWithInvalidTypeAliasImport::class);
    }

    public function testLeaveOutTypeAliasThatCannotBeResolved()
    {
        $typeContext = $this->typeContextFactory->createFromClassName(DummyWithInvalidTypeAlias::class);

        $this->assertSame([], $typeContext->typeAliases);

        $this->expectException(UnsupportedException::class);

        (new StringTypeResolver())->resolve('Invalid', $typeContext);
    }

    public function testThrowWhenTypeAliasNotImportedFromValidClassName()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Type alias "Invalid" is not imported from a valid class name.');

        $this->typeContextFactory->createFromClassName(DummyWithTypeAliasImportedFromInvalidClassName::class);
    }

    public function testLeaveOutRecursiveTypeAliases()
    {
        $this->assertSame([], $this->typeContextFactory->createFromClassName(DummyWithRecursiveTypeAliases::class)->typeAliases);
    }

    public function testThrowWhenImportingTypeAliasesCyclically()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('Cannot import "Foo" type alias from "%s" as it is recursive.', DummyWithMutuallyImportedTypeAliasesA::class));

        $this->typeContextFactory->createFromClassName(DummyWithMutuallyImportedTypeAliasesA::class);
    }
}
