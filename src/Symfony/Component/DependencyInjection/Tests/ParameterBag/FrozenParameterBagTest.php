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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

class FrozenParameterBagTest extends TestCase
{
    public function testConstructor()
    {
        $parameters = array(
            'foo' => 'foo',
            'bar' => 'bar',
            'fooBar' => 'fooBar',
        );
        $bag = new FrozenParameterBag($parameters);
        $this->assertEquals(
            array(
                'foo' => 'foo',
                'bar' => 'bar',
                'foobar' => 'fooBar',
            ),
            $bag->all(),
            '__construct() takes an array of parameters as its first argument and lowercase it keys'
        );
    }

    /**
     * @expectedException \LogicException
     */
    public function testClear()
    {
        $bag = new FrozenParameterBag(array());
        $bag->clear();
    }

    /**
     * @expectedException \LogicException
     */
    public function testSet()
    {
        $bag = new FrozenParameterBag(array());
        $bag->set('foo', 'bar');
    }

    /**
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
