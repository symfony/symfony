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
use Symfony\Component\TypeInfo\Type\NullableType;

class NullableTypeTest extends TestCase
{
    public function testCannotCreateWithNullableType()
    {
        $this->expectException(InvalidArgumentException::class);
        new NullableType(Type::null());
    }

    public function testNullPartIsAdded()
    {
        $type = new NullableType(Type::int());
        $this->assertEquals([Type::int(), Type::null()], $type->getTypes());

        $type = new NullableType(Type::union(Type::int(), Type::string()));
        $this->assertEquals([Type::int(), Type::null(), Type::string()], $type->getTypes());
    }

    public function testWrappedTypeIsSatisfiedBy()
    {
        $type = new NullableType(Type::int());
        $this->assertTrue($type->wrappedTypeIsSatisfiedBy(static fn (Type $t): bool => 'int' === (string) $t));

        $type = new NullableType(Type::string());
        $this->assertFalse($type->wrappedTypeIsSatisfiedBy(static fn (Type $t): bool => 'int' === (string) $t));
    }

    public function testAccepts()
    {
        $type = new NullableType(Type::int());

        $this->assertFalse($type->accepts('string'));
        $this->assertTrue($type->accepts(123));
        $this->assertTrue($type->accepts(null));
    }
}
