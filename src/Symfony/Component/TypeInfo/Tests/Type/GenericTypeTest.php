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
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;
use Symfony\Component\TypeInfo\TypeIdentifier;

class GenericTypeTest extends TestCase
{
    public function testCannotCreateInvalidBuiltinType()
    {
        $this->expectException(InvalidArgumentException::class);
        new GenericType(Type::int(), Type::string());
    }

    public function testToString()
    {
        $type = new GenericType(Type::builtin(TypeIdentifier::ARRAY), Type::bool());
        $this->assertEquals('array<bool>', (string) $type);

        $type = new GenericType(Type::builtin(TypeIdentifier::ARRAY), Type::string(), Type::bool());
        $this->assertEquals('array<string, bool>', (string) $type);

        $type = new GenericType(Type::object(self::class), Type::union(Type::bool(), Type::string()), Type::int(), Type::float());
        $this->assertEquals(\sprintf('%s<bool|string, int, float>', self::class), (string) $type);
    }

    public function testIsIdentifiedBy()
    {
        $type = new GenericType(Type::builtin(TypeIdentifier::ARRAY), Type::bool());
        $this->assertTrue($type->isIdentifiedBy(TypeIdentifier::ARRAY));
        $this->assertTrue($type->isIdentifiedBy('array'));
        $this->assertTrue($type->isIdentifiedBy(TypeIdentifier::INT, TypeIdentifier::ARRAY));
        $this->assertFalse($type->isIdentifiedBy(TypeIdentifier::BOOL));
        $this->assertFalse($type->isIdentifiedBy());

        $type = new GenericType(Type::object(self::class), Type::string());
        $this->assertTrue($type->isIdentifiedBy(TypeIdentifier::OBJECT));
        $this->assertTrue($type->isIdentifiedBy(self::class));
        $this->assertTrue($type->isIdentifiedBy(TestCase::class));
        $this->assertFalse($type->isIdentifiedBy(TypeIdentifier::STRING));
        $this->assertFalse($type->isIdentifiedBy(\stdClass::class));
    }

    public function testIsIdentifiedByWithWrappingMainType()
    {
        $mainType = new class(self::class) extends ObjectType implements WrappingTypeInterface {
            public function getWrappedType(): Type
            {
                return Type::int();
            }

            public function wrappedTypeIsSatisfiedBy(callable $specification): bool
            {
                return $this->getWrappedType()->isSatisfiedBy($specification);
            }
        };

        $type = new GenericType($mainType, Type::string());

        $this->assertTrue($type->isIdentifiedBy(TypeIdentifier::INT));
        $this->assertTrue($type->isIdentifiedBy(TypeIdentifier::OBJECT));
        $this->assertFalse($type->isIdentifiedBy(TypeIdentifier::STRING));
    }

    public function testWrappedTypeIsSatisfiedBy()
    {
        $type = new GenericType(Type::builtin(TypeIdentifier::ARRAY), Type::bool());
        $this->assertTrue($type->wrappedTypeIsSatisfiedBy(static fn (Type $t): bool => 'array' === (string) $t));

        $type = new GenericType(Type::builtin(TypeIdentifier::ITERABLE), Type::bool());
        $this->assertFalse($type->wrappedTypeIsSatisfiedBy(static fn (Type $t): bool => 'array' === (string) $t));
    }

    public function testAccepts()
    {
        $type = new GenericType(Type::object(self::class), Type::string());

        $this->assertFalse($type->accepts('string'));
        $this->assertTrue($type->accepts($this));
    }
}
