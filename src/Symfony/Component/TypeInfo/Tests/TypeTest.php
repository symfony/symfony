<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

class TypeTest extends TestCase
{
    public function testIsIdentifiedBy()
    {
        $this->assertTrue(Type::intersection(Type::object(\Iterator::class), Type::object(\Stringable::class))->isIdentifiedBy(TypeIdentifier::OBJECT));
        $this->assertTrue(Type::union(Type::int(), Type::string())->isIdentifiedBy(TypeIdentifier::INT));
        $this->assertTrue(Type::collection(Type::object(\Iterator::class))->isIdentifiedBy(TypeIdentifier::OBJECT));
        $this->assertTrue(Type::generic(Type::object(\Iterator::class), Type::string())->isIdentifiedBy(TypeIdentifier::OBJECT));
        $this->assertTrue(Type::nullable(Type::union(Type::collection(Type::object(\Iterator::class)), Type::string()))->isIdentifiedBy(TypeIdentifier::OBJECT));
    }

    public function testIsNullable()
    {
        $this->assertTrue(Type::null()->isNullable());
        $this->assertTrue(Type::mixed()->isNullable());
        $this->assertTrue(Type::nullable(Type::int())->isNullable());

        $this->assertFalse(Type::int()->isNullable());
    }

    public function testTraverse()
    {
        $this->assertEquals([Type::int()], iterator_to_array(Type::int()->traverse()));

        $this->assertEquals(
            [Type::union(Type::int(), Type::string()), Type::int(), Type::string()],
            iterator_to_array(Type::union(Type::int(), Type::string())->traverse()),
        );
        $this->assertEquals(
            [Type::union(Type::int(), Type::string())],
            iterator_to_array(Type::union(Type::int(), Type::string())->traverse(traverseComposite: false)),
        );

        $this->assertEquals(
            [Type::generic(Type::object(\Traversable::class), Type::string()), Type::object(\Traversable::class)],
            iterator_to_array(Type::generic(Type::object(\Traversable::class), Type::string())->traverse()),
        );
        $this->assertEquals(
            [Type::generic(Type::object(\Traversable::class), Type::string())],
            iterator_to_array(Type::generic(Type::object(\Traversable::class), Type::string())->traverse(traverseWrapped: false)),
        );

        $this->assertEquals(
            [Type::nullable(Type::int()), Type::int(), Type::null()],
            iterator_to_array(Type::nullable(Type::int())->traverse()),
        );
        $this->assertEquals(
            [Type::nullable(Type::int()), Type::int()],
            iterator_to_array(Type::nullable(Type::int())->traverse(traverseComposite: false)),
        );
        $this->assertEquals(
            [Type::nullable(Type::int()), Type::int(), Type::null()],
            iterator_to_array(Type::nullable(Type::int())->traverse(traverseWrapped: false)),
        );
        $this->assertEquals(
            [Type::nullable(Type::int())],
            iterator_to_array(Type::nullable(Type::int())->traverse(traverseComposite: false, traverseWrapped: false)),
        );
    }
}
