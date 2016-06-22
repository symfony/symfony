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
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidTypeException
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

    public function testNormalizeThrowsExceptionWithoutHint()
    {
        $node = new ScalarNode('test');

        $this->setExpectedException('Symfony\Component\Config\Definition\Exception\InvalidTypeException', 'Invalid type for path "test". Expected scalar, but got array.');

        $node->normalize(array());
    }

    public function testNormalizeThrowsExceptionWithErrorMessage()
    {
        $node = new ScalarNode('test');
        $node->setInfo('"the test value"');

        $this->setExpectedException('Symfony\Component\Config\Definition\Exception\InvalidTypeException', "Invalid type for path \"test\". Expected scalar, but got array.\nHint: \"the test value\"");

        $node->normalize(array());
    }

    /**
     * @dataProvider getValidNonEmptyValues
     *
     * @param mixed $value
     */
    public function testValidNonEmptyValues($value)
    {
        $node = new ScalarNode('test');
        $node->setAllowEmptyValue(false);

        $this->assertSame($value, $node->finalize($value));
    }

    public function getValidNonEmptyValues()
    {
        return array(
            array(false),
            array(true),
            array('foo'),
            array(0),
            array(1),
            array(0.0),
            array(0.1),
        );
    }

    /**
     * @dataProvider getEmptyValues
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     *
     * @param mixed $value
     */
    public function testNotAllowedEmptyValuesThrowException($value)
    {
        $node = new ScalarNode('test');
        $node->setAllowEmptyValue(false);
        $node->finalize($value);
    }

    public function getEmptyValues()
    {
        return array(
            array(null),
            array(''),
        );
    }
}
