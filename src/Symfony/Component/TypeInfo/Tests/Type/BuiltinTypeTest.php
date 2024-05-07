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
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class BuiltinTypeTest extends TestCase
{
    public function testToString()
    {
        $this->assertSame('int', (string) new BuiltinType(TypeIdentifier::INT));
    }

    public function testIsNullable()
    {
        $this->assertFalse((new BuiltinType(TypeIdentifier::INT))->isNullable());
        $this->assertTrue((new BuiltinType(TypeIdentifier::NULL))->isNullable());
        $this->assertTrue((new BuiltinType(TypeIdentifier::MIXED))->isNullable());
    }

    public function testAsNonNullable()
    {
        $type = new BuiltinType(TypeIdentifier::INT);

        $this->assertSame($type, $type->asNonNullable());
        $this->assertEquals(
            Type::union(
                new BuiltinType(TypeIdentifier::OBJECT),
                new BuiltinType(TypeIdentifier::RESOURCE),
                new BuiltinType(TypeIdentifier::ARRAY),
                new BuiltinType(TypeIdentifier::STRING),
                new BuiltinType(TypeIdentifier::FLOAT),
                new BuiltinType(TypeIdentifier::INT),
                new BuiltinType(TypeIdentifier::BOOL),
            ),
            Type::nullable(new BuiltinType(TypeIdentifier::MIXED))->asNonNullable()
        );
    }

    public function testCannotTurnNullAsNonNullable()
    {
        $this->expectException(LogicException::class);

        (new BuiltinType(TypeIdentifier::NULL))->asNonNullable();
    }

    public function testIsA()
    {
        $this->assertFalse((new BuiltinType(TypeIdentifier::INT))->isA(TypeIdentifier::ARRAY));
        $this->assertTrue((new BuiltinType(TypeIdentifier::INT))->isA(TypeIdentifier::INT));
    }
}
