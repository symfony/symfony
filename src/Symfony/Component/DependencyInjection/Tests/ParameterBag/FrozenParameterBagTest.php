<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\ParameterBag;

use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

class FrozenParameterBagTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag::__construct
     */
    public function testConstructor()
    {
        $parameters = array(
            'foo' => 'foo',
            'bar' => 'bar',
        );
        $bag = new FrozenParameterBag($parameters);
        $this->assertEquals($parameters, $bag->all(), '__construct() takes an array of parameters as its first argument');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag::clear
     * @expectedException \LogicException
     */
    public function testClear()
    {
        $bag = new FrozenParameterBag(array());
        $bag->clear();
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag::set
     * @expectedException \LogicException
     */
    public function testSet()
    {
        $bag = new FrozenParameterBag(array());
        $bag->set('foo', 'bar');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag::add
     * @expectedException \LogicException
     */
    public function testAdd()
    {
        $bag = new FrozenParameterBag(array());
        $bag->add(array());
    }

    /**
     * @expectedException \LogicException
     */
    public function testRemove()
    {
        $bag = new FrozenParameterBag(array('foo' => 'bar'));
        $bag->remove('foo');
    }
}
