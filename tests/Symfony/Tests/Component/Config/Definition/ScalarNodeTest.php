<?php

namespace Symfony\Tests\Component\Config\Definition;

use Symfony\Component\Config\Definition\ScalarNode;

class ScalarNodeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getValidValues
     */
    public function testNormalize($value)
    {
        $node = new ScalarNode('test');
        $this->assertSame($value, $node->normalize($value));
    }

    public function getValidValues()
    {
        return array(
            array(false),
            array(true),
            array(null),
            array(''),
            array('foo'),
            array(0),
            array(1),
            array(0.0),
            array(0.1),
        );
    }

    /**
     * @dataProvider getInvalidValues
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidTypeException
     */
    public function testNormalizeThrowsExceptionOnInvalidValues($value)
    {
        $node = new ScalarNode('test');
        $node->normalize($value);
    }

    public function getInvalidValues()
    {
        return array(
            array(array()),
            array(array('foo' => 'bar')),
            array(new \stdClass()),
        );
    }
}