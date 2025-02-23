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
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\IntRangeType;

class IntRangeTypeTest extends TestCase
{
    public function testToString()
    {
        $this->assertSame('int<0, max>', (string) new IntRangeType(from: 0));
        $this->assertSame('int<min, 0>', (string) new IntRangeType(to: 0));
        $this->assertSame('int<1, max>', (string) new IntRangeType(from: 1));
        $this->assertSame('int<min, -1>', (string) new IntRangeType(to: -1));
        $this->assertSame('int<-3, 5>', (string) new IntRangeType(from: -3, to: 5));
        $this->assertSame('int<min, max>', (string) new IntRangeType());
        $this->assertSame('int<min, 5>', (string) new IntRangeType(to: 5));
    }

    public function testAccepts()
    {
        $this->assertFalse((new IntRangeType(from: 0, to: 5))->accepts('string'));
        $this->assertFalse((new IntRangeType(from: 0, to: 5))->accepts([]));

        $this->assertFalse((new IntRangeType(from: -1, to: 5))->accepts(-3));
        $this->assertTrue((new IntRangeType(from: -1, to: 5))->accepts(0));
        $this->assertTrue((new IntRangeType(from: -1, to: 5))->accepts(2));
        $this->assertFalse((new IntRangeType(from: -1, to: 5))->accepts(6));

        $this->assertFalse(Type::union(Type::intRange(to: -1), Type::intRange(from: 1))->accepts(0));
    }
}
