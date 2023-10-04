<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\TypeResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Tests\Fixtures\AbstractDummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\Dummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyEnum;
use Symfony\Component\TypeInfo\Tests\Fixtures\ReflectionExtractableDummy;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContext;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\ReflectionTypeResolver;

class ReflectionTypeResolverTest extends TestCase
{
    private ReflectionTypeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ReflectionTypeResolver();
    }

    /**
     * @dataProvider resolveDataProvider
     */
    public function testResolve(Type $expectedType, \ReflectionType $reflection, TypeContext $typeContext = null)
    {
        $this->assertEquals($expectedType, $this->resolver->resolve($reflection, $typeContext));
    }

    /**
     * @return iterable<array{0: Type, 1: \ReflectionType, 2?: TypeContext}>
     */
    public function resolveDataProvider(): iterable
    {
        $typeContext = (new TypeContextFactory())->createFromClassName(ReflectionExtractableDummy::class);
        $reflection = new \ReflectionClass(ReflectionExtractableDummy::class);

        yield [Type::int(), $reflection->getProperty('builtin')->getType()];
        yield [Type::nullable(Type::int()), $reflection->getProperty('nullableBuiltin')->getType()];
        yield [Type::array(), $reflection->getProperty('array')->getType()];
        yield [Type::nullable(Type::array()), $reflection->getProperty('nullableArray')->getType()];
        yield [Type::iterable(), $reflection->getProperty('iterable')->getType()];
        yield [Type::nullable(Type::iterable()), $reflection->getProperty('nullableIterable')->getType()];
        yield [Type::object(Dummy::class), $reflection->getProperty('class')->getType()];
        yield [Type::nullable(Type::object(Dummy::class)), $reflection->getProperty('nullableClass')->getType()];
        yield [Type::object(ReflectionExtractableDummy::class), $reflection->getProperty('self')->getType(), $typeContext];
        yield [Type::nullable(Type::object(ReflectionExtractableDummy::class)), $reflection->getProperty('nullableSelf')->getType(), $typeContext];
        yield [Type::object(ReflectionExtractableDummy::class), $reflection->getMethod('getStatic')->getReturnType(), $typeContext];
        yield [Type::nullable(Type::object(ReflectionExtractableDummy::class)), $reflection->getMethod('getNullableStatic')->getReturnType(), $typeContext];
        yield [Type::object(AbstractDummy::class), $reflection->getProperty('parent')->getType(), $typeContext];
        yield [Type::nullable(Type::object(AbstractDummy::class)), $reflection->getProperty('nullableParent')->getType(), $typeContext];
        yield [Type::enum(DummyEnum::class), $reflection->getProperty('enum')->getType()];
        yield [Type::nullable(Type::enum(DummyEnum::class)), $reflection->getProperty('nullableEnum')->getType()];
        yield [Type::enum(DummyBackedEnum::class), $reflection->getProperty('backedEnum')->getType()];
        yield [Type::nullable(Type::enum(DummyBackedEnum::class)), $reflection->getProperty('nullableBackedEnum')->getType()];
        yield [Type::union(Type::int(), Type::string()), $reflection->getProperty('union')->getType()];
        yield [Type::intersection(Type::object(\Traversable::class), Type::object(\Stringable::class)), $reflection->getProperty('intersection')->getType()];
    }

    public function testCannotResolveNonProperReflectionType()
    {
        $this->expectException(UnsupportedException::class);
        $this->resolver->resolve(new \ReflectionClass(self::class));
    }

    /**
     * @dataProvider classKeywordsTypesDataProvider
     */
    public function testCannotResolveClassKeywordsWithoutTypeContext(\ReflectionType $reflection)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve($reflection);
    }

    /**
     * @return iterable<array{0: \ReflectionType}>
     */
    public function classKeywordsTypesDataProvider(): iterable
    {
        $reflection = new \ReflectionClass(ReflectionExtractableDummy::class);

        yield [$reflection->getProperty('self')->getType()];
        yield [$reflection->getMethod('getStatic')->getReturnType()];
        yield [$reflection->getProperty('parent')->getType()];
    }
}
