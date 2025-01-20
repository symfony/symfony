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
        $this->assertEquals('array<string,bool>', (string) $type);

        $type = new GenericType(Type::object(self::class), Type::union(Type::bool(), Type::string()), Type::int(), Type::float());
        $this->assertEquals(\sprintf('%s<bool|string,int,float>', self::class), (string) $type);
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
