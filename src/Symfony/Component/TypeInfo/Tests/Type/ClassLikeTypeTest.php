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
use Symfony\Component\TypeInfo\Type\ClassLikeType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class ClassLikeTypeTest extends TestCase
{
    public function testToString()
    {
        $this->assertSame('class-string<Countable>', (string) new ClassLikeType('class-string', Type::object(\Countable::class)));
    }

    public function testGetObjectType()
    {
        $this->assertEquals(Type::object(\Countable::class), (new ClassLikeType('class-string', Type::object(\Countable::class)))->getObjectType());
    }

    public function testIsIdentifiedBy()
    {
        $this->assertTrue((new ClassLikeType('class-string', Type::object(\Countable::class)))->isIdentifiedBy(TypeIdentifier::STRING));
        $this->assertFalse((new ClassLikeType('class-string', Type::object(\Countable::class)))->isIdentifiedBy(TypeIdentifier::OBJECT));
    }

    public function testAccepts()
    {
        $this->assertTrue((new ClassLikeType('class-string', Type::object(\Countable::class)))->accepts(\ArrayObject::class));
        $this->assertFalse((new ClassLikeType('class-string', Type::object(\Countable::class)))->accepts(new \ArrayObject()));
    }
}
