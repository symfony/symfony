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

use Symfony\Component\Config\Definition\ArrayNode;

class ArrayNodeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidTypeException
     */
    public function testNormalizeThrowsExceptionWhenFalseIsNotAllowed()
    {
        $node = new ArrayNode('root');
        $node->normalize(false);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Unrecognized options "foo" under "root"
     */
    public function testExceptionThrownOnUnrecognizedChild()
    {
        $node = new ArrayNode('root');
        $node->normalize(array('foo' => 'bar'));
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

    /**
     * @dataProvider getPreNormalizationTests
     */
    public function testPreNormalize($denormalized, $normalized)
    {
        $node = new ArrayNode('foo');

        $r = new \ReflectionMethod($node, 'preNormalize');
        $r->setAccessible(true);

        $this->assertSame($normalized, $r->invoke($node, $denormalized));
    }

    public function getPreNormalizationTests()
    {
        return array(
            array(
                array('foo-bar' => 'foo'),
                array('foo_bar' => 'foo'),
            ),
            array(
                array('foo-bar_moo' => 'foo'),
                array('foo-bar_moo' => 'foo'),
            ),
            array(
                array('foo-bar' => null, 'foo_bar' => 'foo'),
                array('foo-bar' => null, 'foo_bar' => 'foo'),
            )
        );
    }
}
