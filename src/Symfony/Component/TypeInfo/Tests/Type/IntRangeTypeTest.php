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
use Symfony\Component\TypeInfo\Type\IntRangeType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class IntRangeTypeTest extends TestCase
{
    public function testCannotCreateUnboundedRange()
    {
        $this->expectException(InvalidArgumentException::class);

        new IntRangeType(null, null);
    }

    public function testCannotCreateRangeWithMinGreaterThanMax()
    {
        $this->expectException(InvalidArgumentException::class);

        new IntRangeType(10, 0);
    }

    public function testGetBounds()
    {
        $this->assertSame(0, (new IntRangeType(0, 10))->getMin());
        $this->assertSame(10, (new IntRangeType(0, 10))->getMax());
    }

    public function testIsIdentifiedBy()
    {
        $this->assertTrue((new IntRangeType(0, 10))->isIdentifiedBy(TypeIdentifier::INT));
    }

    public function testAccepts()
    {
        $type = new IntRangeType(0, 10);
        $this->assertTrue($type->accepts(0));
        $this->assertTrue($type->accepts(10));
        $this->assertFalse($type->accepts(-1));
        $this->assertFalse($type->accepts(11));
        $this->assertFalse($type->accepts('5'));

        $this->assertTrue((new IntRangeType(1, null))->accepts(\PHP_INT_MAX));
        $this->assertTrue((new IntRangeType(null, -1))->accepts(\PHP_INT_MIN));
    }

    public function testToString()
    {
        $this->assertSame('int<0, 10>', (string) new IntRangeType(0, 10));
        $this->assertSame('int<1, max>', (string) new IntRangeType(1, null));
        $this->assertSame('int<min, -1>', (string) new IntRangeType(null, -1));
    }
}
