<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\DependencyInjection\ParameterBag;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Exception\NonExistentParameterException;

/**
 * Used for testing global constants
 */
define('PARAMETER_BAG_TEST_CONSTANT', 'bar');

class ParameterBagTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Used for testing class constants
     */
    const TEST_RESOLVE_VALUE_CONSANTS = 'bar';

    /**
     * @covers Symfony\Component\DependencyInjection\ParameterBag\ParameterBag::__construct
     */
    public function testConstructor()
    {
        $bag = new ParameterBag($parameters = array(
            'foo' => 'foo',
            'bar' => 'bar',
        ));
        $this->assertEquals($parameters, $bag->all(), '__construct() takes an array of parameters as its first argument');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ParameterBag\ParameterBag::clear
     */
    public function testClear()
    {
        $bag = new ParameterBag($parameters = array(
            'foo' => 'foo',
            'bar' => 'bar',
        ));
        $bag->clear();
        $this->assertEquals(array(), $bag->all(), '->clear() removes all parameters');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ParameterBag\ParameterBag::get
     * @covers Symfony\Component\DependencyInjection\ParameterBag\ParameterBag::set
     */
    public function testGetSet()
    {
        $bag = new ParameterBag(array('foo' => 'bar'));
        $bag->set('bar', 'foo');
        $this->assertEquals('foo', $bag->get('bar'), '->set() sets the value of a new parameter');

        $bag->set('foo', 'baz');
        $this->assertEquals('baz', $bag->get('foo'), '->set() overrides previously set parameter');

        $bag->set('Foo', 'baz1');
        $this->assertEquals('baz1', $bag->get('foo'), '->set() converts the key to lowercase');
        $this->assertEquals('baz1', $bag->get('FOO'), '->get() converts the key to lowercase');

        try {
            $bag->get('baba');
            $this->fail('->get() throws an \InvalidArgumentException if the key does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->get() throws an \InvalidArgumentException if the key does not exist');
            $this->assertEquals('You have requested a non-existent parameter "baba".', $e->getMessage(), '->get() throws an \InvalidArgumentException if the key does not exist');
        }
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ParameterBag\ParameterBag::has
     */
    public function testHas()
    {
        $bag = new ParameterBag(array('foo' => 'bar'));
        $this->assertTrue($bag->has('foo'), '->has() returns true if a parameter is defined');
        $this->assertTrue($bag->has('Foo'), '->has() converts the key to lowercase');
        $this->assertFalse($bag->has('bar'), '->has() returns false if a parameter is not defined');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ParameterBag\ParameterBag::resolveValue
     */
    public function testResolveValue()
    {
        $bag = new ParameterBag(array());
        $this->assertEquals('foo', $bag->resolveValue('foo'), '->resolveValue() returns its argument unmodified if no placeholders are found');

        $bag = new ParameterBag(array('foo' => 'bar'));
        $this->assertEquals('I\'m a bar', $bag->resolveValue('I\'m a %foo%'), '->resolveValue() replaces placeholders by their values');
        $this->assertEquals(array('bar' => 'bar'), $bag->resolveValue(array('%foo%' => '%foo%')), '->resolveValue() replaces placeholders in keys and values of arrays');
        $this->assertEquals(array('bar' => array('bar' => array('bar' => 'bar'))), $bag->resolveValue(array('%foo%' => array('%foo%' => array('%foo%' => '%foo%')))), '->resolveValue() replaces placeholders in nested arrays');
        $this->assertEquals('I\'m a %foo%', $bag->resolveValue('I\'m a %%foo%%'), '->resolveValue() supports % escaping by doubling it');
        $this->assertEquals('I\'m a bar %foo bar', $bag->resolveValue('I\'m a %foo% %%foo %foo%'), '->resolveValue() supports % escaping by doubling it');

        $bag = new ParameterBag(array('foo' => true));
        $this->assertTrue($bag->resolveValue('%foo%') === true, '->resolveValue() replaces arguments that are just a placeholder by their value without casting them to strings');

        $bag = new ParameterBag(array());
        try {
            $bag->resolveValue('%foobar%', array());
            $this->fail('->resolveValue() throws an InvalidArgumentException if a placeholder references a non-existent parameter');
        } catch (NonExistentParameterException $e) {
            $this->assertEquals('You have requested a non-existent parameter "foobar".', $e->getMessage(), '->resolveValue() throws a NonExistentParameterException if a placeholder references a non-existent parameter');
        }

        try {
            $bag->resolveValue('foo %foobar% bar', array());
            $this->fail('->resolveValue() throws a NonExistentParameterException if a placeholder references a non-existent parameter');
        } catch (NonExistentParameterException $e) {
            $this->assertEquals('You have requested a non-existent parameter "foobar".', $e->getMessage(), '->resolveValue() throws a NonExistentParameterException if a placeholder references a non-existent parameter');
        }

    }

    /**
     * @covers Symfony\Component\DependencyInjection\ParameterBag\ParameterBag::resolveValue
     */
    public function testResolveValueUnknownDiscriminators()
    {

        try {
            $bag = new ParameterBag(array());
            $bag->resolveValue('%FOO:foobar%', array());
            $this->fail('->resolveValue() throws an InvalidArgumentException if a placeholder references an unsupported parameter discriminator');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('You have specified an unsupported parameter discriminator "FOO:foobar".', $e->getMessage(), '->resolveValue() throws a InvalidArgumentException if a placeholder references an unsupported parameter discriminator');
        }

    }

    /**
     * @covers Symfony\Component\DependencyInjection\ParameterBag\ParameterBag::resolveValue
     */
    public function testResolveValueConstants()
    {
  
        try {
            $bag = new ParameterBag(array());
            $bag->resolveValue('%CONSTANT:foobar%', array());
            $this->fail('->resolveValue() throws an InvalidArgumentException if a placeholder references a non-existent constant');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('You have requested a non-existent constant "foobar".', $e->getMessage(), '->resolveValue() throws a InvalidArgumentException if a placeholder references a non-existent constant');
        }

        $this->assertEquals('I\'m a bar', $bag->resolveValue('I\'m a %CONSTANT:PARAMETER_BAG_TEST_CONSTANT%'), '->resolveValue() replaces placeholders by their values (constants)');
        $this->assertEquals('I\'m a bar', $bag->resolveValue('I\'m a %CONSTANT:\Symfony\Tests\Component\DependencyInjection\ParameterBag\ParameterBagTest::TEST_RESOLVE_VALUE_CONSANTS%'), '->resolveValue() replaces placeholders by their values (class constants)');

    }

    /**
     * @covers Symfony\Component\DependencyInjection\ParameterBag\ParameterBag::resolveValue
     */
    public function testResolveValueEnv()
    {
  
        try {
            $bag = new ParameterBag(array());
            $bag->resolveValue('%ENV:DOES_NOT_EXIST%', array());
            $this->fail('->resolveValue() throws an InvalidArgumentException if a placeholder references a non-existent environment variable');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('You have requested a non-existent environment variable "DOES_NOT_EXIST".', $e->getMessage(), '->resolveValue() throws a InvalidArgumentException if a placeholder references a non-existent environment variable');
        }

        $_SERVER['CONSTANT:PARAMETER_BAG_TEST_DOES_NOW_EXIST'] = 'bar';
        $this->assertEquals('I\'m a bar', $bag->resolveValue('I\'m a %ENV:CONSTANT:PARAMETER_BAG_TEST_DOES_NOW_EXIST%'), '->resolveValue() replaces placeholders by their values (environment variable)');

    }

    /**
     * @covers Symfony\Component\DependencyInjection\ParameterBag\ParameterBag::resolve
     */
    public function testResolveIndicatesWhyAParameterIsNeeded()
    {
        $bag = new ParameterBag(array('foo' => '%bar%'));

        try {
            $bag->resolve();
        } catch (NonExistentParameterException $e) {
            $this->assertEquals('The parameter "foo" has a dependency on a non-existent parameter "bar".', $e->getMessage());
        }

        $bag = new ParameterBag(array('foo' => '%bar%'));

        try {
            $bag->resolve();
        } catch (NonExistentParameterException $e) {
            $this->assertEquals('The parameter "foo" has a dependency on a non-existent parameter "bar".', $e->getMessage());
        }
    }
}
