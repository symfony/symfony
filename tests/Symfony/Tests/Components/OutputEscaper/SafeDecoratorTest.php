<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\OutputEscaper;

require_once __DIR__.'/../../bootstrap.php';

use Symfony\Components\OutputEscaper\SafeDecorator;

class SafeDecoratorTest extends \PHPUnit_Framework_TestCase
{
  public function testGetValue()
  {
    $safe = new SafeDecorator('foo');
    $this->assertEquals($safe->getValue(), 'foo', '->getValue() returns the embedded value');
  }

  public function testMagicGetAndSet()
  {
    $safe = new SafeDecorator(new TestClass1());

    $this->assertEquals($safe->foo, 'bar', '->__get() returns the object parameter');
    $safe->foo = 'baz';
    $this->assertEquals($safe->foo, 'baz', '->__set() sets the object parameter');
  }

  public function testMagicCall()
  {
    $safe = new SafeDecorator(new TestClass2());

    $this->assertEquals($safe->doSomething(), 'ok', '->__call() invokes the embedded method');
  }

  public function testMagicIssetAndUnset()
  {
    $safe = new SafeDecorator(new TestClass3());

    $this->assertEquals(isset($safe->boolValue), true, '->__isset() returns true if the property is not null');
    $this->assertEquals(isset($safe->nullValue), false, '->__isset() returns false if the property is null');
    $this->assertEquals(isset($safe->undefinedValue), false, '->__isset() returns false if the property does not exist');

    unset($safe->boolValue);
    $this->assertEquals(isset($safe->boolValue), false, '->__unset() unsets the embedded property');
  }

  public function testIteratorInterface()
  {
    $input = array('one' => 1, 'two' => 2, 'three' => 3, 'children' => array(1, 2, 3));
    $output = array();

    $safe = new SafeDecorator($input);
    foreach ($safe as $key => $value)
    {
      $output[$key] = $value;
    }
    $this->assertSame($output, $input, '"Iterator" implementation imitates an array');
  }

  public function testArrayAccessIterator()
  {
    $safe = new SafeDecorator(array('foo' => 'bar'));

    $this->assertEquals($safe['foo'], 'bar', '"ArrayAccess" implementation returns a value from the embedded array');
    $safe['foo'] = 'baz';
    $this->assertEquals($safe['foo'], 'baz', '"ArrayAccess" implementation sets a value on the embedded array');
    $this->assertEquals(isset($safe['foo']), true, '"ArrayAccess" checks if a value is set on the embedded array');
    unset($safe['foo']);
    $this->assertEquals(isset($safe['foo']), false, '"ArrayAccess" unsets a value on the embedded array');
  }
}

class TestClass1
{
  public $foo = 'bar';
}

class TestClass2
{
  public function doSomething()
  {
    return 'ok';
  }
}

class TestClass3
{
  public
    $boolValue = true,
    $nullValue = null;
}
