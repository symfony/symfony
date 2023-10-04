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
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyEnum;
use Symfony\Component\TypeInfo\Type\EnumType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class EnumTypeTest extends TestCase
{
    public function testToString()
    {
        $this->assertSame(DummyEnum::class, (string) new EnumType(DummyEnum::class));
    }

    public function testIsNullable()
    {
        $this->assertFalse((new EnumType(DummyEnum::class))->isNullable());
    }

    public function testAsNonNullable()
    {
        $type = new EnumType(DummyEnum::class);

        $this->assertSame($type, $type->asNonNullable());
    }

    public function testIsA()
    {
        $this->assertFalse((new EnumType(DummyEnum::class))->isA(TypeIdentifier::ARRAY));
        $this->assertTrue((new EnumType(DummyEnum::class))->isA(TypeIdentifier::OBJECT));
    }
}
