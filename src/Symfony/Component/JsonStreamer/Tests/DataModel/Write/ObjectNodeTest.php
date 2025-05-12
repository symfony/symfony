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
use Symfony\Component\JsonStreamer\DataModel\FunctionDataAccessor;
use Symfony\Component\JsonStreamer\DataModel\PropertyDataAccessor;
use Symfony\Component\JsonStreamer\DataModel\VariableDataAccessor;
use Symfony\Component\JsonStreamer\DataModel\Write\ObjectNode;
use Symfony\Component\JsonStreamer\DataModel\Write\ScalarNode;
use Symfony\Component\TypeInfo\Type;

class ObjectNodeTest extends TestCase
{
    public function testWithAccessor()
    {
        $object = new ObjectNode(new VariableDataAccessor('foo'), Type::object(self::class), [
            new ScalarNode(new PropertyDataAccessor(new VariableDataAccessor('foo'), 'property'), Type::int()),
            new ScalarNode(new FunctionDataAccessor('function', [], new VariableDataAccessor('foo')), Type::int()),
            new ScalarNode(new FunctionDataAccessor('function', []), Type::int()),
            new ScalarNode(new VariableDataAccessor('bar'), Type::int()),
        ]);
        $object = $object->withAccessor($newAccessor = new VariableDataAccessor('baz'));

        $this->assertSame($newAccessor, $object->getAccessor());
        $this->assertSame($newAccessor, $object->getProperties()[0]->getAccessor()->getObjectAccessor());
        $this->assertSame($newAccessor, $object->getProperties()[1]->getAccessor()->getObjectAccessor());
        $this->assertNull($object->getProperties()[2]->getAccessor()->getObjectAccessor());
        $this->assertNotSame($newAccessor, $object->getProperties()[3]->getAccessor());
    }
}
