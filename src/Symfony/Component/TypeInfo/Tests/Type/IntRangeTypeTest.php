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
use Symfony\Component\TypeInfo\TypeIdentifier;

class IntRangeTypeTest extends TestCase
{
    public function testToString()
    {
        $this->assertSame('int<-3, 5>', (string) new IntRangeType(-3, 5));
        $this->assertSame('int<1, max>', (string) new IntRangeType(from: 1));
        $this->assertSame('int<min, 0>', (string) new IntRangeType(to: 0));
        $this->assertSame('int<min, max>', (string) new IntRangeType());
        $this->assertSame('int<1, max>', (string) new IntRangeType(from: 1, explicitType: 'positive-int'));
    }

    public function testGetExplicitType()
    {
        $this->assertSame('int<1, max>', (new IntRangeType(from: 1))->getExplicitType());
        $this->assertSame('positive-int', (new IntRangeType(from: 1, explicitType: 'positive-int'))->getExplicitType());
    }

    public function testIsIdentifiedBy()
    {
        $this->assertTrue((new IntRangeType(0, 5))->isIdentifiedBy(TypeIdentifier::INT));
        $this->assertFalse((new IntRangeType(0, 5))->isIdentifiedBy(TypeIdentifier::STRING));
    }

    public function testAccepts()
    {
        $this->assertFalse((new IntRangeType(-1, 5))->accepts('string'));
        $this->assertFalse((new IntRangeType(-1, 5))->accepts(1.0));
        $this->assertFalse((new IntRangeType(-1, 5))->accepts(-2));
        $this->assertTrue((new IntRangeType(-1, 5))->accepts(-1));
        $this->assertTrue((new IntRangeType(-1, 5))->accepts(5));
        $this->assertFalse((new IntRangeType(-1, 5))->accepts(6));

        $this->assertFalse(Type::union(Type::intRange(to: -1), Type::intRange(from: 1))->accepts(0));
        $this->assertTrue(Type::union(Type::intRange(to: -1), Type::intRange(from: 1))->accepts(1));
    }
}
