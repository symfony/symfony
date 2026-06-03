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
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class ObjectTypeTest extends TestCase
{
    public function testToString()
    {
        $this->assertSame(self::class, (string) new ObjectType(self::class));
    }

    public function testIsIdentifiedBy()
    {
        $this->assertFalse((new ObjectType(self::class))->isIdentifiedBy(TypeIdentifier::ARRAY));
        $this->assertTrue((new ObjectType(self::class))->isIdentifiedBy(TypeIdentifier::OBJECT));

        $this->assertFalse((new ObjectType(self::class))->isIdentifiedBy('array'));
        $this->assertTrue((new ObjectType(self::class))->isIdentifiedBy('object'));

        $this->assertTrue((new ObjectType(self::class))->isIdentifiedBy(self::class));
        $this->assertFalse((new ObjectType(self::class))->isIdentifiedBy(\stdClass::class));

        $this->assertTrue((new ObjectType(self::class))->isIdentifiedBy('array', 'object'));
    }

    public function testAccepts()
    {
        $this->assertFalse((new ObjectType(self::class))->accepts('string'));
        $this->assertFalse((new ObjectType(self::class))->accepts(new \stdClass()));
        $this->assertTrue((new ObjectType(parent::class))->accepts($this));
        $this->assertTrue((new ObjectType(self::class))->accepts($this));
    }
}
