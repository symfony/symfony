<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

class TypeTest extends TestCase
{
    public function testIs()
    {
        $isInt = fn (Type $t) => TypeIdentifier::INT === $t->getBaseType()->getTypeIdentifier();

        $this->assertTrue(Type::int()->is($isInt));
        $this->assertTrue(Type::union(Type::string(), Type::int())->is($isInt));
        $this->assertTrue(Type::generic(Type::int(), Type::string())->is($isInt));

        $this->assertFalse(Type::string()->is($isInt));
        $this->assertFalse(Type::union(Type::string(), Type::float())->is($isInt));
        $this->assertFalse(Type::generic(Type::string(), Type::int())->is($isInt));
    }

    public function testIsNullable()
    {
        $this->assertTrue(Type::null()->isNullable());
        $this->assertTrue(Type::mixed()->isNullable());
        $this->assertTrue(Type::nullable(Type::int())->isNullable());
        $this->assertTrue(Type::union(Type::int(), Type::null())->isNullable());
        $this->assertTrue(Type::union(Type::int(), Type::mixed())->isNullable());
        $this->assertTrue(Type::generic(Type::null(), Type::string())->isNullable());

        $this->assertFalse(Type::int()->isNullable());
        $this->assertFalse(Type::union(Type::int(), Type::string())->isNullable());
        $this->assertFalse(Type::generic(Type::int(), Type::nullable(Type::string()))->isNullable());
        $this->assertFalse(Type::generic(Type::int(), Type::mixed())->isNullable());
    }

    public function testCannotGetBaseTypeOnCompoundType()
    {
        $this->expectException(LogicException::class);
        Type::union(Type::int(), Type::string())->getBaseType();
    }

    public function testThrowsOnUnexistingMethod()
    {
        $this->expectException(LogicException::class);
        Type::int()->unexistingMethod();
    }
}
