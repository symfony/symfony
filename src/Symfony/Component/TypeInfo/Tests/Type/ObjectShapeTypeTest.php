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
use Symfony\Component\TypeInfo\Type\ObjectShapeType;

class ObjectShapeTypeTest extends TestCase
{
    public function testAccepts()
    {
        $type = new ObjectShapeType([
            'foo' => ['type' => Type::bool(), 'optional' => false],
            'bar' => ['type' => Type::string(), 'optional' => true],
        ]);

        $this->assertFalse($type->accepts('string'));
        $this->assertFalse($type->accepts((object) []));
        $this->assertFalse($type->accepts((object) ['foo' => 'string']));
        $this->assertFalse($type->accepts((object) ['foo' => true, 'other' => 'string']));
        $this->assertFalse($type->accepts(['foo' => true]));
        $this->assertFalse($type->accepts(['foo' => true, 'bar' => 'string']));

        $this->assertTrue($type->accepts((object) ['foo' => true]));
        $this->assertTrue($type->accepts((object) ['foo' => true, 'bar' => 'string']));
    }

    public function testToString()
    {
        $type = new ObjectShapeType([1 => ['type' => Type::bool(), 'optional' => false]]);
        $this->assertSame("object{'1': bool}", (string) $type);

        $type = new ObjectShapeType([
            2 => ['type' => Type::int(), 'optional' => true],
            1 => ['type' => Type::bool(), 'optional' => false],
        ]);
        $this->assertSame("object{'1': bool, '2'?: int}", (string) $type);

        $type = new ObjectShapeType([
            'foo' => ['type' => Type::bool(), 'optional' => false],
            'bar' => ['type' => Type::string(), 'optional' => true],
        ]);
        $this->assertSame("object{'bar'?: string, 'foo': bool}", (string) $type);
    }
}
