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
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class BuiltinTypeTest extends TestCase
{
    public function testToString()
    {
        $this->assertSame('int', (string) new BuiltinType(TypeIdentifier::INT));
    }

    public function testIsIdentifiedBy()
    {
        $this->assertFalse((new BuiltinType(TypeIdentifier::INT))->isIdentifiedBy(TypeIdentifier::ARRAY));
        $this->assertTrue((new BuiltinType(TypeIdentifier::INT))->isIdentifiedBy(TypeIdentifier::INT));

        $this->assertFalse((new BuiltinType(TypeIdentifier::INT))->isIdentifiedBy('array'));
        $this->assertTrue((new BuiltinType(TypeIdentifier::INT))->isIdentifiedBy('int'));

        $this->assertTrue((new BuiltinType(TypeIdentifier::INT))->isIdentifiedBy('string', 'int'));
    }

    public function testIsNullable()
    {
        $this->assertTrue((new BuiltinType(TypeIdentifier::NULL))->isNullable());
        $this->assertTrue((new BuiltinType(TypeIdentifier::MIXED))->isNullable());
        $this->assertFalse((new BuiltinType(TypeIdentifier::INT))->isNullable());
    }

    public function testAccepts()
    {
        $this->assertFalse((new BuiltinType(TypeIdentifier::ARRAY))->accepts('string'));
        $this->assertTrue((new BuiltinType(TypeIdentifier::ARRAY))->accepts([]));

        $this->assertFalse((new BuiltinType(TypeIdentifier::BOOL))->accepts('string'));
        $this->assertTrue((new BuiltinType(TypeIdentifier::BOOL))->accepts(true));

        $this->assertFalse((new BuiltinType(TypeIdentifier::CALLABLE))->accepts('string'));
        $this->assertTrue((new BuiltinType(TypeIdentifier::CALLABLE))->accepts('strtoupper'));

        $this->assertFalse((new BuiltinType(TypeIdentifier::FALSE))->accepts('string'));
        $this->assertTrue((new BuiltinType(TypeIdentifier::FALSE))->accepts(false));

        $this->assertFalse((new BuiltinType(TypeIdentifier::FLOAT))->accepts('string'));
        $this->assertTrue((new BuiltinType(TypeIdentifier::FLOAT))->accepts(1.23));

        $this->assertFalse((new BuiltinType(TypeIdentifier::INT))->accepts('string'));
        $this->assertTrue((new BuiltinType(TypeIdentifier::INT))->accepts(123));

        $this->assertFalse((new BuiltinType(TypeIdentifier::ITERABLE))->accepts('string'));
        $this->assertTrue((new BuiltinType(TypeIdentifier::ITERABLE))->accepts([]));

        $this->assertTrue((new BuiltinType(TypeIdentifier::MIXED))->accepts('string'));

        $this->assertFalse((new BuiltinType(TypeIdentifier::NULL))->accepts('string'));
        $this->assertTrue((new BuiltinType(TypeIdentifier::NULL))->accepts(null));

        $this->assertFalse((new BuiltinType(TypeIdentifier::OBJECT))->accepts('string'));
        $this->assertTrue((new BuiltinType(TypeIdentifier::OBJECT))->accepts(new \stdClass()));

        $this->assertFalse((new BuiltinType(TypeIdentifier::RESOURCE))->accepts('string'));
        $this->assertTrue((new BuiltinType(TypeIdentifier::RESOURCE))->accepts(fopen('php://temp', 'r')));

        $this->assertFalse((new BuiltinType(TypeIdentifier::STRING))->accepts(123));
        $this->assertTrue((new BuiltinType(TypeIdentifier::STRING))->accepts('string'));

        $this->assertFalse((new BuiltinType(TypeIdentifier::TRUE))->accepts('string'));
        $this->assertTrue((new BuiltinType(TypeIdentifier::TRUE))->accepts(true));

        $this->assertFalse((new BuiltinType(TypeIdentifier::NEVER))->accepts('string'));
        $this->assertFalse((new BuiltinType(TypeIdentifier::VOID))->accepts('string'));
    }
}
