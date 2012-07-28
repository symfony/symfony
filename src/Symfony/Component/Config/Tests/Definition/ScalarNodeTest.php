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

    public function testNormalizeThrowsExceptionWithoutErrorMessage()
    {
        $value = array(array('foo' => 'bar'));
        $node = new ScalarNode('test');

        $expectedMessage = sprintf(
            'Invalid type for path "%s". Expected scalar, but got %s.',
            $node->getPath(),
            gettype($value)
        );

        $this->setExpectedException(
            'Symfony\Component\Config\Definition\Exception\InvalidTypeException', $expectedMessage
        );

        $node->normalize($value);
    }

    public function testNormalizeThrowsExceptionWithErrorMessage()
    {
        $value = array(array('foo' => 'bar'));
        $node = new ScalarNode('test');
        $node->setInfo('This is a custom error message');

        $expectedMessage = sprintf(
            'Invalid type for path "%s". Expected scalar, but got %s.'.
                "\nHint: ".$node->getInfo(),
            $node->getPath(),
            gettype($value)
        );

        $this->setExpectedException(
            'Symfony\Component\Config\Definition\Exception\InvalidTypeException', $expectedMessage
        );

        $node->normalize($value);
    }
}
