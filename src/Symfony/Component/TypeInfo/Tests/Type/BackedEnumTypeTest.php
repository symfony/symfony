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
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class BackedEnumTypeTest extends TestCase
{
    public function testToString()
    {
        $this->assertSame(DummyBackedEnum::class, (string) new BackedEnumType(DummyBackedEnum::class, Type::int()));
    }

    public function testIsNullable()
    {
        $this->assertFalse((new BackedEnumType(DummyBackedEnum::class, Type::int()))->isNullable());
    }

    public function testAsNonNullable()
    {
        $type = new BackedEnumType(DummyBackedEnum::class, Type::int());

        $this->assertSame($type, $type->asNonNullable());
    }

    public function testIsA()
    {
        $this->assertFalse((new BackedEnumType(DummyBackedEnum::class, Type::int()))->isA(TypeIdentifier::ARRAY));
        $this->assertTrue((new BackedEnumType(DummyBackedEnum::class, Type::int()))->isA(TypeIdentifier::OBJECT));
    }
}
