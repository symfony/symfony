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
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class GenericTypeTest extends TestCase
{
    public function testToString()
    {
        $type = new GenericType(Type::builtin(TypeIdentifier::ARRAY), Type::bool());
        $this->assertEquals('array<bool>', (string) $type);

        $type = new GenericType(Type::builtin(TypeIdentifier::ARRAY), Type::string(), Type::bool());
        $this->assertEquals('array<string,bool>', (string) $type);

        $type = new GenericType(Type::object(self::class), Type::union(Type::bool(), Type::string()), Type::int(), Type::float());
        $this->assertEquals(\sprintf('%s<bool|string,int,float>', self::class), (string) $type);
    }

    public function testGetBaseType()
    {
        $this->assertEquals(Type::object(), Type::generic(Type::object(), Type::int())->getBaseType());
    }

    public function testIsNullable()
    {
        $this->assertFalse((new GenericType(Type::builtin(TypeIdentifier::ARRAY), Type::int()))->isNullable());
        $this->assertTrue((new GenericType(Type::null(), Type::int()))->isNullable());
        $this->assertTrue((new GenericType(Type::mixed(), Type::int()))->isNullable());
    }

    public function testAsNonNullable()
    {
        $type = new GenericType(Type::builtin(TypeIdentifier::ARRAY), Type::int());

        $this->assertSame($type, $type->asNonNullable());
    }

    public function testIsA()
    {
        $type = new GenericType(Type::builtin(TypeIdentifier::ARRAY), Type::string(), Type::bool());
        $this->assertTrue($type->isA(TypeIdentifier::ARRAY));
        $this->assertFalse($type->isA(TypeIdentifier::STRING));
        $this->assertFalse($type->isA(self::class));

        $type = new GenericType(Type::object(self::class), Type::union(Type::bool(), Type::string()), Type::int(), Type::float());
        $this->assertTrue($type->isA(TypeIdentifier::OBJECT));
        $this->assertFalse($type->isA(TypeIdentifier::INT));
        $this->assertFalse($type->isA(TypeIdentifier::STRING));
        $this->assertTrue($type->isA(self::class));
    }

    public function testProxiesMethodsToBaseType()
    {
        $type = new GenericType(Type::object(self::class), Type::float());
        $this->assertSame(self::class, $type->getClassName());
    }
}
