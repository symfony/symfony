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

use Symfony\Component\Config\Definition\EnumNode;

class EnumNodeTest extends \PHPUnit_Framework_TestCase
{
    public function testFinalizeValue()
    {
        $node = new EnumNode('foo', null, array('foo', 'bar'));
        $this->assertSame('foo', $node->finalize('foo'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructionWithOneValue()
    {
        new EnumNode('foo', null, array('foo', 'foo'));
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage The value "foobar" is not allowed for path "foo". Permissible values: "foo", "bar"
     */
    public function testFinalizeWithInvalidValue()
    {
        $node = new EnumNode('foo', null, array('foo', 'bar'));
        $node->finalize('foobar');
    }
}
