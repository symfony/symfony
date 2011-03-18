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
     * normalize() should protect against child values with no corresponding node
     */
    public function testExceptionThrownOnUnrecognizedChild()
    {
        $node = new ArrayNode('root');

        try
        {
            $node->normalize(array('foo' => 'bar'));
            $this->fail('An exception should have been throw for a bad child node');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\Component\Config\Definition\Exception\InvalidConfigurationException', $e);
            $this->assertEquals('Unrecognized options "foo" under "root"', $e->getMessage());
        }
    }

    /**
     * Tests that no exception is thrown for an unrecognized child if the
     * ignoreExtraKeys option is set to true.
     *
     * Related to testExceptionThrownOnUnrecognizedChild
     */
    public function testIgnoreExtraKeysNoException()
    {
        $node = new ArrayNode('roo');
        $node->setIgnoreExtraKeys(true);

        $node->normalize(array('foo' => 'bar'));
        $this->assertTrue(true, 'No exception was thrown when setIgnoreExtraKeys is true');
    }
}
