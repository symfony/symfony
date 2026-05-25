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
use Symfony\Component\TypeInfo\TypeIdentifier;

class ObjectShapeTypeTest extends TestCase
{
    public function testGetTypeIdentifier()
    {
        $type = new ObjectShapeType(['foo' => ['type' => Type::bool(), 'optional' => false]]);
        $this->assertSame(TypeIdentifier::OBJECT, $type->getTypeIdentifier());

        $this->assertSame(TypeIdentifier::OBJECT, (new ObjectShapeType([]))->getTypeIdentifier());
    }

    public function testGetShapeIsSortedByKey()
    {
        $shape = [
            'foo' => ['type' => Type::bool(), 'optional' => false],
            'bar' => ['type' => Type::string(), 'optional' => true],
        ];

        $type = new ObjectShapeType($shape);
        ksort($shape);

        $this->assertSame($shape, $type->getShape());
    }

    public function testIsIdentifiedByObject()
    {
        $type = new ObjectShapeType(['foo' => ['type' => Type::bool(), 'optional' => false]]);

        $this->assertTrue($type->isIdentifiedBy(TypeIdentifier::OBJECT));
        $this->assertTrue($type->isIdentifiedBy('object'));
        $this->assertTrue($type->isIdentifiedBy(TypeIdentifier::INT, TypeIdentifier::OBJECT));
        $this->assertFalse($type->isIdentifiedBy(TypeIdentifier::ARRAY));
        $this->assertFalse($type->isIdentifiedBy(TypeIdentifier::STRING));

        $this->assertTrue((new ObjectShapeType([]))->isIdentifiedBy(TypeIdentifier::OBJECT));
    }

    public function testEmptyShape()
    {
        $type = new ObjectShapeType([]);

        $this->assertSame([], $type->getShape());
        $this->assertSame('object{}', (string) $type);
        $this->assertTrue($type->accepts((object) []));
        $this->assertFalse($type->accepts((object) ['foo' => true]));
        $this->assertFalse($type->accepts('string'));
    }

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

    public function testAcceptsRejectsRequiredNonPublicProperty()
    {
        $object = new class {
            public bool $foo = true;
            private string $bar = 'hidden';
        };

        $type = new ObjectShapeType([
            'foo' => ['type' => Type::bool(), 'optional' => false],
            'bar' => ['type' => Type::string(), 'optional' => false],
        ]);
        $this->assertFalse($type->accepts($object));
    }

    public function testAcceptsIgnoresNonPublicProperty()
    {
        $object = new class {
            public bool $foo = true;
            private string $bar = 'hidden';
        };

        $type = new ObjectShapeType(['foo' => ['type' => Type::bool(), 'optional' => false]]);
        $this->assertTrue($type->accepts($object));
    }

    public function testAcceptsRejectsUninitializedRequiredProperty()
    {
        $object = new class {
            public string $foo;
            public bool $bar = true;
        };

        $type = new ObjectShapeType([
            'foo' => ['type' => Type::string(), 'optional' => false],
            'bar' => ['type' => Type::bool(), 'optional' => false],
        ]);
        $this->assertFalse($type->accepts($object));
    }

    public function testAcceptsAllowsUninitializedOptionalProperty()
    {
        $object = new class {
            public string $foo;
            public bool $bar = true;
        };

        $type = new ObjectShapeType([
            'foo' => ['type' => Type::string(), 'optional' => true],
            'bar' => ['type' => Type::bool(), 'optional' => false],
        ]);
        $this->assertTrue($type->accepts($object));
    }

    public function testAcceptsWithOptionalKeyOmitted()
    {
        $type = new ObjectShapeType([
            'foo' => ['type' => Type::bool()],
            'bar' => ['type' => Type::string(), 'optional' => true],
        ]);

        $this->assertTrue($type->accepts((object) ['foo' => true]));
        $this->assertTrue($type->accepts((object) ['foo' => true, 'bar' => 'baz']));
        $this->assertFalse($type->accepts((object) []));
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
