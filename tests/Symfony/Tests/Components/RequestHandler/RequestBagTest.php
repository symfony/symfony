<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\RequestHandler;

use Symfony\Components\RequestHandler\RequestBag;

class RequestBagTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @covers Symfony\Components\RequestHandler\RequestBag::__construct
   */
  public function testConstructor()
  {
    $this->testAll();
  }

  /**
   * @covers Symfony\Components\RequestHandler\RequestBag::all
   */
  public function testAll()
  {
    $bag = new RequestBag(array('foo' => 'bar'));
    $this->assertEquals(array('foo' => 'bar'), $bag->all(), '->all() gets all the input');
  }

  /**
   * @covers Symfony\Components\RequestHandler\RequestBag::replace
   */
  public function testReplace()
  {
    $bag = new RequestBag(array('foo' => 'bar'));

    $bag->replace(array('FOO' => 'BAR'));
    $this->assertEquals(array('FOO' => 'BAR'), $bag->all(), '->replace() replaces the input with the argument');
    $this->assertFalse($bag->has('foo'), '->replace() overrides previously set the input');
  }

  /**
   * @covers Symfony\Components\RequestHandler\RequestBag::get
   */
  public function testGet()
  {
    $bag = new RequestBag(array('foo' => 'bar', 'null' => null));

    $this->assertEquals('bar', $bag->get('foo'), '->get() gets the value of a parameter');
    $this->assertEquals('default', $bag->get('unknown', 'default'), '->get() returns second argument as default if a parameter is not defined');
    $this->assertNull($bag->get('null', 'default'), '->get() retuns null if null is set');
  }

  /**
   * @covers Symfony\Components\RequestHandler\RequestBag::set
   */
  public function testSet()
  {
    $bag = new RequestBag(array());

    $bag->set('foo', 'bar');
    $this->assertEquals('bar', $bag->get('foo'), '->set() sets the value of parameter');

    $bag->set('foo', 'baz');
    $this->assertEquals('baz', $bag->get('foo'), '->set() overrides previously set parameter');
  }

  /**
   * @covers Symfony\Components\RequestHandler\RequestBag::has
   */
  public function testHas()
  {
    $bag = new RequestBag(array('foo' => 'bar'));

    $this->assertTrue($bag->has('foo'), '->has() returns true if a parameter is defined');
    $this->assertFalse($bag->has('unknown'), '->has() return false if a parameter is not defined');
  }

  /**
   * @covers Symfony\Components\RequestHandler\RequestBag::getAlpha
   */
  public function testGetAlpha()
  {
    $bag = new RequestBag(array('word' => 'foo_BAR_012'));

    $this->assertEquals('fooBAR', $bag->getAlpha('word'), '->getAlpha() gets only alphabetic characters');
    $this->assertEquals('', $bag->getAlpha('unknown'), '->getAlpha() returns empty string if a parameter is not defined');
  }

  /**
   * @covers Symfony\Components\RequestHandler\RequestBag::getAlnum
   */
  public function testGetAlnum()
  {
    $bag = new RequestBag(array('word' => 'foo_BAR_012'));

    $this->assertEquals('fooBAR012', $bag->getAlnum('word'), '->getAlnum() gets only alphanumeric characters');
    $this->assertEquals('', $bag->getAlnum('unknown'), '->getAlnum() returns empty string if a parameter is not defined');
  }

  /**
   * @covers Symfony\Components\RequestHandler\RequestBag::getDigits
   */
  public function testGetDigits()
  {
    $bag = new RequestBag(array('word' => 'foo_BAR_012'));

    $this->assertEquals('012', $bag->getDigits('word'), '->getDigits() gets only digits as string');
    $this->assertEquals('', $bag->getDigits('unknown'), '->getDigits() returns empty string if a parameter is not defined');
  }

  /**
   * @covers Symfony\Components\RequestHandler\RequestBag::getInt
   */
  public function testGetInt()
  {
    $bag = new RequestBag(array('digits' => '0123'));

    $this->assertEquals(123, $bag->getInt('digits'), '->getInt() gets a value of parameter as integer');
    $this->assertEquals(0, $bag->getInt('unknown'), '->getInt() returns zero if a parameter is not defined');
  }
}

