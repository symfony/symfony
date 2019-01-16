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
        $parameters = [
            'foo' => 'foo',
            'bar' => 'bar',
        ];
        $bag = new FrozenParameterBag($parameters);
        $this->assertEquals($parameters, $bag->all(), '__construct() takes an array of parameters as its first argument');
    }

    /**
     * @expectedException \LogicException
     */
    public function testClear()
    {
        $bag = new FrozenParameterBag([]);
        $bag->clear();
    }

    /**
     * @expectedException \LogicException
     */
    public function testSet()
    {
        $bag = new FrozenParameterBag([]);
        $bag->set('foo', 'bar');
    }

    /**
     * @expectedException \LogicException
     */
    public function testAdd()
    {
        $bag = new FrozenParameterBag([]);
        $bag->add([]);
    }

    /**
     * @expectedException \LogicException
     */
    public function testRemove()
    {
        $bag = new FrozenParameterBag(['foo' => 'bar']);
        $bag->remove('foo');
    }
}
