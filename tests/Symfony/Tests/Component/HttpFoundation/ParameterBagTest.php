<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\ParameterBag;

class ParameterBagTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\HttpFoundation\ParameterBag::__construct
     */
    public function testConstructor()
    {
        $this->testAll();
    }

    /**
     * @covers Symfony\Component\HttpFoundation\ParameterBag::all
     */
    public function testAll()
    {
        $bag = new ParameterBag(array('foo' => 'bar'));
        $this->assertEquals(array('foo' => 'bar'), $bag->all(), '->all() gets all the input');
    }

    /**
     * @covers Symfony\Component\HttpFoundation\ParameterBag::replace
     */
    public function testReplace()
    {
        $bag = new ParameterBag(array('foo' => 'bar'));

        $bag->replace(array('FOO' => 'BAR'));
        $this->assertEquals(array('FOO' => 'BAR'), $bag->all(), '->replace() replaces the input with the argument');
        $this->assertFalse($bag->has('foo'), '->replace() overrides previously set the input');
    }

    /**
     * @covers Symfony\Component\HttpFoundation\ParameterBag::get
     */
    public function testGet()
    {
        $bag = new ParameterBag(array('foo' => 'bar', 'null' => null));

        $this->assertEquals('bar', $bag->get('foo'), '->get() gets the value of a parameter');
        $this->assertEquals('default', $bag->get('unknown', 'default'), '->get() returns second argument as default if a parameter is not defined');
        $this->assertNull($bag->get('null', 'default'), '->get() returns null if null is set');
    }

    /**
     * @covers Symfony\Component\HttpFoundation\ParameterBag::set
     */
    public function testSet()
    {
        $bag = new ParameterBag(array());

        $bag->set('foo', 'bar');
        $this->assertEquals('bar', $bag->get('foo'), '->set() sets the value of parameter');

        $bag->set('foo', 'baz');
        $this->assertEquals('baz', $bag->get('foo'), '->set() overrides previously set parameter');
    }

    /**
     * @covers Symfony\Component\HttpFoundation\ParameterBag::has
     */
    public function testHas()
    {
        $bag = new ParameterBag(array('foo' => 'bar'));

        $this->assertTrue($bag->has('foo'), '->has() returns true if a parameter is defined');
        $this->assertFalse($bag->has('unknown'), '->has() return false if a parameter is not defined');
    }

    /**
     * @covers Symfony\Component\HttpFoundation\ParameterBag::getAlpha
     */
    public function testGetAlpha()
    {
        $bag = new ParameterBag(array('word' => 'foo_BAR_012'));

        $this->assertEquals('fooBAR', $bag->getAlpha('word'), '->getAlpha() gets only alphabetic characters');
        $this->assertEquals('', $bag->getAlpha('unknown'), '->getAlpha() returns empty string if a parameter is not defined');
    }

    /**
     * @covers Symfony\Component\HttpFoundation\ParameterBag::getAlnum
     */
    public function testGetAlnum()
    {
        $bag = new ParameterBag(array('word' => 'foo_BAR_012'));

        $this->assertEquals('fooBAR012', $bag->getAlnum('word'), '->getAlnum() gets only alphanumeric characters');
        $this->assertEquals('', $bag->getAlnum('unknown'), '->getAlnum() returns empty string if a parameter is not defined');
    }

    /**
     * @covers Symfony\Component\HttpFoundation\ParameterBag::getDigits
     */
    public function testGetDigits()
    {
        $bag = new ParameterBag(array('word' => 'foo_BAR_012'));

        $this->assertEquals('012', $bag->getDigits('word'), '->getDigits() gets only digits as string');
        $this->assertEquals('', $bag->getDigits('unknown'), '->getDigits() returns empty string if a parameter is not defined');
    }

    /**
     * @covers Symfony\Component\HttpFoundation\ParameterBag::getInt
     */
    public function testGetInt()
    {
        $bag = new ParameterBag(array('digits' => '0123'));

        $this->assertEquals(123, $bag->getInt('digits'), '->getInt() gets a value of parameter as integer');
        $this->assertEquals(0, $bag->getInt('unknown'), '->getInt() returns zero if a parameter is not defined');
    }
}

