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
use Symfony\Component\TypeInfo\Type\IntRangeType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class IntRangeTypeTest extends TestCase
{
    public function testToString()
    {
        $this->assertSame('non-negative-int', (string) new IntRangeType(0, \PHP_INT_MAX));
        $this->assertSame('non-positive-int', (string) new IntRangeType(\PHP_INT_MIN, 0));
        $this->assertSame('positive-int', (string) new IntRangeType(1, \PHP_INT_MAX));
        $this->assertSame('negative-int', (string) new IntRangeType(\PHP_INT_MIN, -1));
        $this->assertSame('int<-3, 5>', (string) new IntRangeType(-3, 5));
        $this->assertSame('int<min, max>', (string) new IntRangeType(\PHP_INT_MIN, \PHP_INT_MAX));
        $this->assertSame('int<min, 5>', (string) new IntRangeType(\PHP_INT_MIN, 5));
    }

    public function testIsIdentifiedBy()
    {
        $this->assertFalse((new IntRangeType(0, 5))->isIdentifiedBy(TypeIdentifier::ARRAY));
        $this->assertTrue((new BuiltinType(TypeIdentifier::INT))->isIdentifiedBy(TypeIdentifier::INT));

        $this->assertFalse((new IntRangeType(0, 5))->isIdentifiedBy('array'));
        $this->assertTrue((new IntRangeType(0, 5))->isIdentifiedBy('int'));

        $this->assertTrue((new IntRangeType(0, 5))->isIdentifiedBy('string', 'int'));
    }

    public function testIsNullable()
    {
        $this->assertFalse((new IntRangeType(0, 5))->isNullable());
    }

    public function testAccepts()
    {
        $this->assertFalse((new IntRangeType(0, 5))->accepts('string'));
        $this->assertFalse((new IntRangeType(0, 5))->accepts([]));

        $this->assertFalse((new IntRangeType(-1, 5))->accepts(-3));
        $this->assertTrue((new IntRangeType(-1, 5))->accepts(0));
        $this->assertTrue((new IntRangeType(-1, 5))->accepts(2));
        $this->assertFalse((new IntRangeType(-1, 5))->accepts(6));

        $this->assertFalse((new IntRangeType(\PHP_INT_MIN, \PHP_INT_MAX, false))->accepts(0));
    }
}
