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
use Symfony\Component\TypeInfo\Type\LiteralType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class LiteralTypeTest extends TestCase
{
    public function testCannotCreateNonFiniteFloat()
    {
        $this->expectException(InvalidArgumentException::class);

        new LiteralType(\INF);
    }

    public function testGetValue()
    {
        $this->assertSame('draft', (new LiteralType('draft'))->getValue());
    }

    public function testGetWrappedType()
    {
        $this->assertEquals(Type::string(), (new LiteralType('draft'))->getWrappedType());
        $this->assertEquals(Type::int(), (new LiteralType(1))->getWrappedType());
        $this->assertEquals(Type::float(), (new LiteralType(1.5))->getWrappedType());
    }

    public function testIsIdentifiedBy()
    {
        $this->assertTrue((new LiteralType('draft'))->isIdentifiedBy(TypeIdentifier::STRING));
        $this->assertFalse((new LiteralType('draft'))->isIdentifiedBy(TypeIdentifier::INT));
    }

    public function testAccepts()
    {
        $this->assertTrue((new LiteralType('draft'))->accepts('draft'));
        $this->assertFalse((new LiteralType('draft'))->accepts('published'));
        $this->assertFalse((new LiteralType(1))->accepts('1'));
    }

    public function testToString()
    {
        $this->assertSame("'draft'", (string) new LiteralType('draft'));
        $this->assertSame("'it\\'s'", (string) new LiteralType("it's"));
        $this->assertSame('1.0', (string) new LiteralType(1.0));

        if (8 === \PHP_INT_SIZE) {
            $this->assertSame('-9223372036854775808', (string) new LiteralType(\PHP_INT_MIN));
        }

        $this->assertSame('"a\\x0Ab"', (string) new LiteralType("a\nb"));
    }
}
