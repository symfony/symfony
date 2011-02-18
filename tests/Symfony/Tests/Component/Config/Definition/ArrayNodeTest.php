<?php

namespace Symfony\Tests\Component\Config\Definition;

use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\ScalarNode;

class ArrayNodeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidTypeException
     */
    public function testNormalizeThrowsExceptionWhenFalseIsNotAllowed()
    {
        $node = new ArrayNode('root');
        $node->normalize(false);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSetDefaultValueThrowsExceptionWhenNotAnArray()
    {
        $node = new ArrayNode('root');
        $node->setDefaultValue('test');
    }

    /**
     * @expectedException RuntimeException
     */
    public function testSetDefaultValueThrowsExceptionWhenNotAnPrototype()
    {
        $node = new ArrayNode('root');
        $node->setDefaultValue(array ('test'));
    }

    public function testGetDefaultValueReturnsAnEmptyArrayForPrototypes()
    {
        $node = new ArrayNode('root');
        $prototype = new ArrayNode(null, $node);
        $node->setPrototype($prototype);
        $this->assertEmpty($node->getDefaultValue());
    }

    public function testGetDefaultValueReturnsDefaultValueForPrototypes()
    {
        $node = new ArrayNode('root');
        $prototype = new ArrayNode(null, $node);
        $node->setPrototype($prototype);
        $node->setDefaultValue(array ('test'));
        $this->assertEquals(array ('test'), $node->getDefaultValue());
    }

    // finalizeValue() should protect against child values with no corresponding node
    public function testExceptionThrownOnUnrecognizedChild()
    {
        $this->setExpectedException('Symfony\Component\Config\Definition\Exception\InvalidConfigurationException');
        $node = new ArrayNode('root');
        $node->normalize(array('foo' => 'bar'));
    }

    // if prevent extra keys is false, normalize allows them
    public function textNoExceptionForUnrecognizedChildWithUnnamedChildren()
    {
        $node = new ArrayNode('root');
        $node->setPreventExtraKeys(false);
        $normalized = $node->normalize(array('foo' => 'bar'));

        $this->assertEquals(array('foo' => 'bar'), $normalized);
    }

    // a remapped key (e.g. "mapping" -> "mappings") should be unset after being used
    public function testRemappedKeysAreUnset()
    {
        $node = new ArrayNode('root');
        $mappingsNode = new ArrayNode('mappings');
        $mappingsNode->setPreventExtraKeys(false); // just so we can add anything to it
        $node->addChild($mappingsNode);

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
        $node = new ArrayNode('root');
        $node->setKeyAttribute('id');

        $prototype = new ArrayNode(null);
        $prototype->setPreventExtraKeys(false); // just so it allows anything
        $node->setPrototype($prototype);

        $children = array();
        $children[] = array('id' => 'item_name', 'foo' => 'bar');
        $normalized = $node->normalize($children);

        $expected = array();
        $expected['item_name'] = array('foo' => 'bar');
        $this->assertEquals($expected, $normalized);
    }
}
