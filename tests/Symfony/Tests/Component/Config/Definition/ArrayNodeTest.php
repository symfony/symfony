<?php

namespace Symfony\Tests\Component\Config\Definition;

use Symfony\Component\Config\Definition\ArrayNode;

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
        $this->setExpectedException('Symfony\Component\DependencyInjection\Configuration\Exception\InvalidConfigurationException');
        $node = new ArrayNode('root');
        $node->finalize(array('foo' => 'bar'));
    }

    // if unnamedChildren is true, finalize allows them
    public function textNoExceptionForUnrecognizedChildWithUnnamedChildren()
    {
        $node = new ArrayNode('root');
        $node->setAllowUnnamedChildren(true);
        $finalized = $node->finalize(array('foo' => 'bar'));

        $this->assertEquals(array('foo' => 'bar'), $finalized);
    }

    /**
     * normalize() should not strip values that don't have children nodes.
     * Validation will take place later in finalizeValue().
     */
    public function testNormalizeKeepsExtraArrayValues()
    {
        $node = new ArrayNode('root');
        $normalized = $node->normalize(array('foo' => 'bar'));
        $this->assertEquals(array('foo' => 'bar'), $normalized);
    }
}