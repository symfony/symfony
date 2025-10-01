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

use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Tests\Fixtures\AbstractDummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\Dummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithImportedOnlyTypeAliases;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithInvalidTypeAlias;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithInvalidTypeAliasImport;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithRecursiveTypeAliases;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithTemplates;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithTypeAliases;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithTypeAliasImportedFromInvalidClassName;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithUses;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithUsesWindowsLineEndings;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;

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
        $this->assertSame('Dummy', $typeContext->calledClassName);
        $this->assertSame('AbstractDummy', $typeContext->declaringClassName);

        $typeContext = $this->typeContextFactory->createFromReflection(new \ReflectionClass(Dummy::class));
        $this->assertSame('Dummy', $typeContext->calledClassName);
        $this->assertSame('Dummy', $typeContext->declaringClassName);

        $typeContext = $this->typeContextFactory->createFromReflection(new \ReflectionProperty(Dummy::class, 'id'));
        $this->assertSame('Dummy', $typeContext->calledClassName);
        $this->assertSame('Dummy', $typeContext->declaringClassName);

        $typeContext = $this->typeContextFactory->createFromReflection(new \ReflectionMethod(Dummy::class, 'getId'));
        $this->assertSame('Dummy', $typeContext->calledClassName);
        $this->assertSame('Dummy', $typeContext->declaringClassName);

        $typeContext = $this->typeContextFactory->createFromReflection(new \ReflectionParameter([Dummy::class, 'setId'], 'id'));
        $this->assertSame('Dummy', $typeContext->calledClassName);
        $this->assertSame('Dummy', $typeContext->declaringClassName);
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
            \DateTimeInterface::class => '\\'.\DateTimeInterface::class,
            'DateTime' => '\\'.\DateTimeImmutable::class,
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
            \DateTimeInterface::class => '\\'.\DateTimeInterface::class,
            'DateTime' => '\\'.\DateTimeImmutable::class,
        ];

        $this->assertSame($uses, $this->typeContextFactory->createFromClassName(DummyWithUsesWindowsLineEndings::class)->uses);

        $this->assertEquals($uses, $this->typeContextFactory->createFromReflection(new \ReflectionClass(DummyWithUsesWindowsLineEndings::class))->uses);
        $this->assertEquals($uses, $this->typeContextFactory->createFromReflection(new \ReflectionProperty(DummyWithUsesWindowsLineEndings::class, 'createdAt'))->uses);
        $this->assertEquals($uses, $this->typeContextFactory->createFromReflection(new \ReflectionMethod(DummyWithUsesWindowsLineEndings::class, 'setCreatedAt'))->uses);
        $this->assertEquals($uses, $this->typeContextFactory->createFromReflection(new \ReflectionParameter([DummyWithUsesWindowsLineEndings::class, 'setCreatedAt'], 'createdAt'))->uses);
    }

    public function testCollectTemplates()
    {
        $this->assertEquals([], $this->typeContextFactory->createFromClassName(Dummy::class)->templates);
        $this->assertEquals([
            'T' => Type::union(Type::int(), Type::string()),
            'U' => Type::mixed(),
        ], $this->typeContextFactory->createFromClassName(DummyWithTemplates::class)->templates);

        $this->assertEquals([
            'T' => Type::union(Type::int(), Type::string()),
            'U' => Type::mixed(),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionClass(DummyWithTemplates::class))->templates);

        $this->assertEquals([
            'T' => Type::union(Type::int(), Type::string()),
            'U' => Type::mixed(),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionProperty(DummyWithTemplates::class, 'price'))->templates);

        $this->assertEquals([
            'T' => Type::union(Type::int(), Type::float()),
            'U' => Type::mixed(),
            'V' => Type::mixed(),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionMethod(DummyWithTemplates::class, 'getPrice'))->templates);

        $this->assertEquals([
            'T' => Type::union(Type::int(), Type::float()),
            'U' => Type::mixed(),
            'V' => Type::mixed(),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionParameter([DummyWithTemplates::class, 'getPrice'], 'inCents'))->templates);
    }

    public function testDoNotCollectTemplatesWhenToStringTypeResolver()
    {
        $typeContextFactory = new TypeContextFactory();

        $this->assertEquals([], $typeContextFactory->createFromClassName(DummyWithTemplates::class)->templates);
    }

    public function testCollectTypeAliases()
    {
        $this->assertEquals([
            'CustomString' => Type::string(),
            'CustomInt' => Type::int(),
            'CustomArray' => Type::arrayShape([0 => Type::int(), 1 => Type::string(), 2 => Type::bool()]),
            'AliasedCustomInt' => Type::int(),
            'PsalmCustomString' => Type::string(),
            'PsalmCustomInt' => Type::int(),
            'PsalmCustomArray' => Type::arrayShape([0 => Type::int(), 1 => Type::string(), 2 => Type::bool()]),
            'PsalmAliasedCustomInt' => Type::int(),
        ], $this->typeContextFactory->createFromClassName(DummyWithTypeAliases::class)->typeAliases);

        $this->assertEquals([
            'CustomString' => Type::string(),
            'CustomInt' => Type::int(),
            'CustomArray' => Type::arrayShape([0 => Type::int(), 1 => Type::string(), 2 => Type::bool()]),
            'AliasedCustomInt' => Type::int(),
            'PsalmCustomString' => Type::string(),
            'PsalmCustomInt' => Type::int(),
            'PsalmCustomArray' => Type::arrayShape([0 => Type::int(), 1 => Type::string(), 2 => Type::bool()]),
            'PsalmAliasedCustomInt' => Type::int(),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionClass(DummyWithTypeAliases::class))->typeAliases);

        $this->assertEquals([
            'CustomString' => Type::string(),
            'CustomInt' => Type::int(),
            'CustomArray' => Type::arrayShape([0 => Type::int(), 1 => Type::string(), 2 => Type::bool()]),
            'AliasedCustomInt' => Type::int(),
            'PsalmCustomString' => Type::string(),
            'PsalmCustomInt' => Type::int(),
            'PsalmCustomArray' => Type::arrayShape([0 => Type::int(), 1 => Type::string(), 2 => Type::bool()]),
            'PsalmAliasedCustomInt' => Type::int(),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionProperty(DummyWithTypeAliases::class, 'localAlias'))->typeAliases);

        $this->assertEquals([
            'CustomInt' => Type::int(),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionClass(DummyWithImportedOnlyTypeAliases::class))->typeAliases);
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

    public function testThrowWhenCannotResolveTypeAlias()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot resolve "Invalid" type alias.');

        $this->typeContextFactory->createFromClassName(DummyWithInvalidTypeAlias::class);
    }

    public function testThrowWhenTypeAliasNotImportedFromValidClassName()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Type alias "Invalid" is not imported from a valid class name.');

        $this->typeContextFactory->createFromClassName(DummyWithTypeAliasImportedFromInvalidClassName::class);
    }

    public function testThrowWhenImportingRecursiveTypeAliases()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot resolve "Bar" type alias.');

        $this->typeContextFactory->createFromClassName(DummyWithRecursiveTypeAliases::class)->typeAliases;
    }
}
