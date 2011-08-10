<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Tests\Component\Config\Definition;

use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\ScalarNode;

class PrototypedArrayNodeTest extends \PHPUnit_Framework_TestCase
{

    public function testGetDefaultValueReturnsAnEmptyArrayForPrototypes()
    {
        $node = new PrototypedArrayNode('root');
        $prototype = new ArrayNode(null, $node);
        $node->setPrototype($prototype);
        $this->assertEmpty($node->getDefaultValue());
    }

    public function testGetDefaultValueReturnsDefaultValueForPrototypes()
    {
        $node = new PrototypedArrayNode('root');
        $prototype = new ArrayNode(null, $node);
        $node->setPrototype($prototype);
        $node->setDefaultValue(array ('test'));
        $this->assertEquals(array ('test'), $node->getDefaultValue());
    }

    // a remapped key (e.g. "mapping" -> "mappings") should be unset after being used
    public function testRemappedKeysAreUnset()
    {
        $node = new ArrayNode('root');
        $mappingsNode = new PrototypedArrayNode('mappings');
        $node->addChild($mappingsNode);

        // each item under mappings is just a scalar
        $prototype= new ScalarNode(null, $mappingsNode);
        $mappingsNode->setPrototype($prototype);

        $remappings = array();
        $remappings[] = array('mapping', 'mappings');
        $node->setXmlRemappings($remappings);

        $normalized = $node->normalize(array('mapping' => array('foo', 'bar')));
        $this->assertEquals(array('mappings' => array('foo', 'bar')), $normalized);
    }

    /**
     * Tests that when a key attribute is mapped, that key is removed from the array:
     *
     *     <things>
     *         <option id="option1" value="foo">
     *         <option id="option2" value="bar">
     *     </things>
     *
     * The above should finally be mapped to an array that looks like this
     * (because "id" is the key attribute).
     *
     *     array(
     *         'things' => array(
     *             'option1' => 'foo',
     *             'option2' => 'bar',
     *         )
     *     )
     */
    public function testMappedAttributeKeyIsRemoved()
    {
        $node = new PrototypedArrayNode('root');
        $node->setKeyAttribute('id', true);

        // each item under the root is an array, with one scalar item
        $prototype= new ArrayNode(null, $node);
        $prototype->addChild(new ScalarNode('foo'));
        $node->setPrototype($prototype);

        $children = array();
        $children[] = array('id' => 'item_name', 'foo' => 'bar');
        $normalized = $node->normalize($children);

        $expected = array();
        $expected['item_name'] = array('foo' => 'bar');
        $this->assertEquals($expected, $normalized);
    }

    /**
     * Tests the opposite of the testMappedAttributeKeyIsRemoved because
     * the removal can be toggled with an option.
     */
    public function testMappedAttributeKeyNotRemoved()
    {
        $node = new PrototypedArrayNode('root');
        $node->setKeyAttribute('id', false);

        // each item under the root is an array, with two scalar items
        $prototype= new ArrayNode(null, $node);
        $prototype->addChild(new ScalarNode('foo'));
        $prototype->addChild(new ScalarNode('id')); // the key attribute will remain
        $node->setPrototype($prototype);

        $children = array();
        $children[] = array('id' => 'item_name', 'foo' => 'bar');
        $normalized = $node->normalize($children);

        $expected = array();
        $expected['item_name'] = array('id' => 'item_name', 'foo' => 'bar');
        $this->assertEquals($expected, $normalized);
    }
}
