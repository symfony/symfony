<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\SyntaxAware;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\ParameterCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\SyntaxAware\SyntaxAwareParameterBag;
use Symfony\Component\ExpressionLanguage\Expression;

class SyntaxAwareParameterBagTest extends \PHPUnit_Framework_TestCase
{
    public function testGetParameterBag()
    {
        $innerBag = new ParameterBag();
        $bag = new SyntaxAwareParameterBag($innerBag);
        $this->assertSame($innerBag, $bag->getParameterBag());
    }

    public function testAdd()
    {
        $innerBag = new ParameterBag();
        $bag = new SyntaxAwareParameterBag($innerBag);

        $bag->add(array('should_cook' => true, 'food' => '@=parameter("kernel.debug") ? "debug_pizza" : "pizza"'));
        $actual = $bag->all();
        $this->assertEquals(array('should_cook' => true, 'food' => new Expression('parameter("kernel.debug") ? "debug_pizza" : "pizza"')), $actual);
    }

    public function testSet()
    {
        $innerBag = new ParameterBag();
        $bag = new SyntaxAwareParameterBag($innerBag);

        $bag->set('should_cook', true);
        $bag->set('food', '@=parameter("kernel.debug") ? "debug_pizza" : "pizza"');
        $actual = $bag->all();
        $this->assertEquals(array('should_cook' => true, 'food' => new Expression('parameter("kernel.debug") ? "debug_pizza" : "pizza"')), $actual);
    }

    public function testClear()
    {
        $innerBag = $this->getMock('Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface');
        $innerBag->expects($this->once())
            ->method('clear');

        $bag = new SyntaxAwareParameterBag($innerBag);
        $bag->clear();
    }

    public function testAll()
    {
        $innerBag = $this->getMock('Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface');
        $innerBag->expects($this->once())
            ->method('all')
            ->will($this->returnValue(array('foo' => 'bar')));

        $bag = new SyntaxAwareParameterBag($innerBag);
        $actual = $bag->all();
        $this->assertEquals(array('foo' => 'bar'), $actual);
    }

    public function testGet()
    {
        $innerBag = $this->getMock('Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface');
        $innerBag->expects($this->once())
            ->method('get')
            ->with('foo')
            ->will($this->returnValue('bar'));

        $bag = new SyntaxAwareParameterBag($innerBag);
        $actual = $bag->get('foo');
        $this->assertEquals('bar', $actual);
    }

    public function testHas()
    {
        $innerBag = $this->getMock('Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface');
        $innerBag->expects($this->once())
            ->method('has')
            ->with('foo')
            ->will($this->returnValue(true));

        $bag = new SyntaxAwareParameterBag($innerBag);
        $actual = $bag->has('foo');
        $this->assertEquals(true, $actual);
    }

    public function testResolve()
    {
        $innerBag = $this->getMock('Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface');
        $innerBag->expects($this->once())
            ->method('resolve');

        $bag = new SyntaxAwareParameterBag($innerBag);
        $bag->resolve();
    }

    public function testResolveValue()
    {
        $innerBag = $this->getMock('Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface');
        $innerBag->expects($this->once())
            ->method('resolveValue')
            ->with('%foo%')
            ->will($this->returnValue('foo_resolved'));

        $bag = new SyntaxAwareParameterBag($innerBag);
        $actual = $bag->resolveValue('%foo%');
        $this->assertEquals('foo_resolved', $actual);
    }

    public function testEscapeValue()
    {
        $innerBag = $this->getMock('Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface');
        $innerBag->expects($this->once())
            ->method('escapeValue')
            ->with('%foo%')
            ->will($this->returnValue('%%foo%%'));

        $bag = new SyntaxAwareParameterBag($innerBag);
        $actual = $bag->escapeValue('%foo%');
        $this->assertEquals('%%foo%%', $actual);
    }

    public function testUnescapeValue()
    {
        $innerBag = $this->getMock('Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface');
        $innerBag->expects($this->once())
            ->method('unescapeValue')
            ->with('%%foo%%')
            ->will($this->returnValue('%foo%'));

        $bag = new SyntaxAwareParameterBag($innerBag);
        $actual = $bag->unescapeValue('%%foo%%');
        $this->assertEquals('%foo%', $actual);
    }
}
