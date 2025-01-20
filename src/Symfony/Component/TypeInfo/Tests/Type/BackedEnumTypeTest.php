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
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BackedEnumType;

class BackedEnumTypeTest extends TestCase
{
    public function testCannotCreateInvalidBackingBuiltinType()
    {
        $this->expectException(InvalidArgumentException::class);
        new BackedEnumType(DummyBackedEnum::class, Type::bool());
    }

    public function testToString()
    {
        $this->assertSame(DummyBackedEnum::class, (string) new BackedEnumType(DummyBackedEnum::class, Type::int()));
    }

    public function testAccepts()
    {
        $type = new BackedEnumType(DummyBackedEnum::class, Type::int());

        $this->assertFalse($type->accepts('string'));
        $this->assertTrue($type->accepts(DummyBackedEnum::ONE));
    }
}
