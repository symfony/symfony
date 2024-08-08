<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Type;

use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class IntersectionTypeTest extends TestCase
{
    public function testCannotCreateWithOnlyOneType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IntersectionType(Type::object('Foo'));
    }

    public static function getInvalidParts(): iterable
    {
        $foo = Type::object('Foo');
        $bar = Type::object('Bar');

        yield 'intersection' => [Type::intersection($foo, $bar), Type::intersection($foo, $bar)];
        yield 'union' => [Type::intersection($foo, $bar), Type::intersection($foo, $bar)];
        foreach (TypeIdentifier::cases() as $case) {
            yield $case->value => [Type::builtin($case), Type::builtin($case)];
        }
        yield 'generic<builtin>' => [Type::object('Foo'), Type::generic(Type::builtin('array'), Type::string())];
        yield 'collection<builtin>' => [Type::object('Foo'), Type::collection(Type::generic(Type::builtin('array')))];
    }

    /**
     * @dataProvider getInvalidParts
     */
    public function testCannotCreateWithNonObjectParts(Type ...$parts): void
    {
        $this->expectException(InvalidArgumentException::class);

        new IntersectionType(...$parts);
    }

    public function testCreateWithObjectParts(): void
    {
        $foo = Type::object('Foo');
        $bar = Type::generic(Type::object('Bar'), Type::string());
        $baz = Type::collection(Type::generic(Type::object('Baz'), Type::string()));

        $type = new IntersectionType($foo, $bar, $baz);
        $this->assertEquals([$bar, $baz, $foo], $type->getTypes());
    }

    public function testAtLeastOneTypeIs(): void
    {
        $type = new IntersectionType(Type::object('Foo'), Type::object('Bar'), Type::object('Baz'));

        $this->assertTrue($type->atLeastOneTypeIs(fn (Type $t) => 'Bar' === (string) $t));
        $this->assertFalse($type->atLeastOneTypeIs(fn (Type $t) => 'Blip' === (string) $t));
    }

    public function testEveryTypeIs()
    {
        $type = new IntersectionType(Type::object('Foo'), Type::object('Bar'), Type::object('Baz'));
        $this->assertTrue($type->everyTypeIs(fn (Type $t) => $t instanceof ObjectType));

        $type = new IntersectionType(Type::object('Foo'), Type::object('Bar'), Type::generic(Type::object('Baz')));
        $this->assertFalse($type->everyTypeIs(fn (Type $t) => $t instanceof ObjectType));
    }

    public function testGetBaseType()
    {
        $this->expectException(LogicException::class);
        (new IntersectionType(Type::object('Bar'), Type::object('Foo')))->getBaseType();
    }

    public function testToString()
    {
        $type = new IntersectionType(Type::object('Foo'), Type::object('Bar'), Type::generic(Type::object('Baz'), Type::string()));
        $this->assertSame('Bar&Baz<string>&Foo', (string) $type);
    }

    public function testIsA()
    {
        $type = new IntersectionType(Type::object('Foo'), Type::object('Bar'));
        $this->assertFalse($type->isA(TypeIdentifier::ARRAY));

        $type = new IntersectionType(Type::object('Foo'), Type::object('Bar'));
        $this->assertFalse($type->isA(TypeIdentifier::INT));

        $type = new IntersectionType(Type::object('Foo'), Type::object('Bar'));
        $this->assertTrue($type->isA(TypeIdentifier::OBJECT));
    }
}
