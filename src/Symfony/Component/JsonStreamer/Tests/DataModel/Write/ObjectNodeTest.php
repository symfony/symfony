<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\DataModel\Write;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\DataModel\Write\ObjectNode;
use Symfony\Component\JsonStreamer\DataModel\Write\ScalarNode;
use Symfony\Component\TypeInfo\Type;

class ObjectNodeTest extends TestCase
{
    public function testWithAccessor()
    {
        $object = new ObjectNode('$foo', Type::object(self::class), [
            new ScalarNode('$foo->property', Type::int()),
            new ScalarNode('$foo->method()', Type::int()),
            new ScalarNode('function()', Type::int()),
            new ScalarNode('$bar', Type::int()),
        ]);
        $object = $object->withAccessor('$baz');

        $this->assertSame('$baz', $object->getAccessor());
        $this->assertSame('$baz->property', $object->getProperties()[0]->getAccessor());
        $this->assertSame('$baz->method()', $object->getProperties()[1]->getAccessor());
        $this->assertSame('function()', $object->getProperties()[2]->getAccessor());
        $this->assertSame('$bar', $object->getProperties()[3]->getAccessor());
    }
}
