<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition;

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

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage The value 4 is too small for path "foo". Should be greater than: 5
     */
    public function testMinAssertion()
    {
        $node = new ScalarNode('foo');
        $node->min(5);
        $node->finalize(4);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage The value 4 is too big for path "foo". Should be less than: 3
     */
    public function testMaxAssertion()
    {
        $node = new ScalarNode('foo');
        $node->max(3);
        $node->finalize(4);
    }

    public function testValidMinMaxAssertion()
    {
        $node = new ScalarNode('foo');
        $node->min(3)->max(7);
        $this->assertEquals(4, $node->finalize(4));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage You cannot define a min(4) as you already have a max(3)
     */
    public function testIncoherentMinAssertion()
    {
        $node = new ScalarNode('foo');
        $node->max(3)->min(4);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage You cannot define a max(2) as you already have a min(3)
     */
    public function testIncoherentMaxAssertion()
    {
        $node = new ScalarNode('foo');
        $node->min(3)->max(2);
    }

}
