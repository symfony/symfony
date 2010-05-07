<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\OutputEscaper;

use Symfony\Components\OutputEscaper\SafeDecorator;

class SafeDecoratorTest extends \PHPUnit_Framework_TestCase
{
    public function testGetValue()
    {
        $safe = new SafeDecorator('foo');
        $this->assertEquals('foo', $safe->getValue(), '->getValue() returns the embedded value');
    }

    public function testMagicGetAndSet()
    {
        $safe = new SafeDecorator(new TestClass1());

        $this->assertEquals('bar', $safe->foo, '->__get() returns the object parameter');
        $safe->foo = 'baz';
        $this->assertEquals('baz', $safe->foo, '->__set() sets the object parameter');
    }

    public function testMagicCall()
    {
        $safe = new SafeDecorator(new TestClass2());

        $this->assertEquals('ok', $safe->doSomething(), '->__call() invokes the embedded method');
    }

    public function testMagicIssetAndUnset()
    {
        $safe = new SafeDecorator(new TestClass3());

        $this->assertTrue(isset($safe->boolValue), '->__isset() returns true if the property is not null');
        $this->assertFalse(isset($safe->nullValue), '->__isset() returns false if the property is null');
        $this->assertFalse(isset($safe->undefinedValue), '->__isset() returns false if the property does not exist');

        unset($safe->boolValue);
        $this->assertFalse(isset($safe->boolValue), '->__unset() unsets the embedded property');
    }

    public function testIteratorInterface()
    {
        $input = array('one' => 1, 'two' => 2, 'three' => 3, 'children' => array(1, 2, 3));
        $output = array();

        $safe = new SafeDecorator($input);
        foreach ($safe as $key => $value) {
            $output[$key] = $value;
        }
        $this->assertSame($output, $input, '"Iterator" implementation imitates an array');
    }

    public function testArrayAccessIterator()
    {
        $safe = new SafeDecorator(array('foo' => 'bar'));

        $this->assertEquals('bar', $safe['foo'], '"ArrayAccess" implementation returns a value from the embedded array');
        $safe['foo'] = 'baz';
        $this->assertEquals('baz', $safe['foo'], '"ArrayAccess" implementation sets a value on the embedded array');
        $this->assertTrue(isset($safe['foo']), '"ArrayAccess" checks if a value is set on the embedded array');
        unset($safe['foo']);
        $this->assertFalse(isset($safe['foo']), '"ArrayAccess" unsets a value on the embedded array');
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
