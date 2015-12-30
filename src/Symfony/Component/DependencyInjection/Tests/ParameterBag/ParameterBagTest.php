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

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

class ParameterBagTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $bag = new ParameterBag($parameters = array(
            'foo' => 'foo',
            'bar' => 'bar',
        ));
        $this->assertEquals($parameters, $bag->all(), '__construct() takes an array of parameters as its first argument');
    }

    public function testClear()
    {
        $bag = new ParameterBag($parameters = array(
            'foo' => 'foo',
            'bar' => 'bar',
        ));
        $bag->clear();
        $this->assertEquals(array(), $bag->all(), '->clear() removes all parameters');
    }

    public function testRemove()
    {
        $bag = new ParameterBag(array(
            'foo' => 'foo',
            'bar' => 'bar',
        ));
        $bag->remove('foo');
        $this->assertEquals(array('bar' => 'bar'), $bag->all(), '->remove() removes a parameter');
        $bag->remove('BAR');
        $this->assertEquals(array(), $bag->all(), '->remove() converts key to lowercase before removing');
    }

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
            $this->fail('->get() throws an Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException if the key does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException', $e, '->get() throws an Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException if the key does not exist');
            $this->assertEquals('You have requested a non-existent parameter "baba".', $e->getMessage(), '->get() throws an Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException if the key does not exist');
        }
    }

    /**
     * @dataProvider provideGetThrowParameterNotFoundExceptionData
     */
    public function testGetThrowParameterNotFoundException($parameterKey, $exceptionMessage)
    {
        $bag = new ParameterBag(array(
            'foo' => 'foo',
            'bar' => 'bar',
            'baz' => 'baz',
            'fiz' => array('bar' => array('boo' => 12)),
        ));

        $this->setExpectedException('Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException', $exceptionMessage);

        $bag->get($parameterKey);
    }

    public function provideGetThrowParameterNotFoundExceptionData()
    {
        return array(
            array('foo1', 'You have requested a non-existent parameter "foo1". Did you mean this: "foo"?'),
            array('bag', 'You have requested a non-existent parameter "bag". Did you mean one of these: "bar", "baz"?'),
            array('', 'You have requested a non-existent parameter "".'),

            array('fiz.bar.boo', 'You have requested a non-existent parameter "fiz.bar.boo". You cannot access nested array items, do you want to inject "fiz" instead?'),
        );
    }

    public function testHas()
    {
        $bag = new ParameterBag(array('foo' => 'bar'));
        $this->assertTrue($bag->has('foo'), '->has() returns true if a parameter is defined');
        $this->assertTrue($bag->has('Foo'), '->has() converts the key to lowercase');
        $this->assertFalse($bag->has('bar'), '->has() returns false if a parameter is not defined');
    }

    public function testResolveIndicatesWhyAParameterIsNeeded()
    {
        $bag = new ParameterBag(array('foo' => '%bar%'));

        try {
            $bag->resolve();
        } catch (ParameterNotFoundException $e) {
            $this->assertEquals('The parameter "foo" has a dependency on a non-existent parameter "bar".', $e->getMessage());
        }

        $bag = new ParameterBag(array('foo' => '%bar%'));

        try {
            $bag->resolve();
        } catch (ParameterNotFoundException $e) {
            $this->assertEquals('The parameter "foo" has a dependency on a non-existent parameter "bar".', $e->getMessage());
        }
    }

    public function testResolveUnescapesValue()
    {
        $bag = new ParameterBag(array(
            'foo' => array('bar' => array('ding' => 'I\'m a bar %%foo %%bar')),
            'bar' => 'I\'m a %%foo%%',
        ));

        $bag->resolve();

        $this->assertEquals('I\'m a %foo%', $bag->get('bar'), '->resolveValue() supports % escaping by doubling it');
        $this->assertEquals(array('bar' => array('ding' => 'I\'m a bar %foo %bar')), $bag->get('foo'), '->resolveValue() supports % escaping by doubling it');
    }

    public function testEscapeValue()
    {
        $bag = new ParameterBag();

        $bag->add(array(
            'foo' => $bag->escapeValue(array('bar' => array('ding' => 'I\'m a bar %foo %bar', 'zero' => null))),
            'bar' => $bag->escapeValue('I\'m a %foo%'),
        ));

        $this->assertEquals('I\'m a %%foo%%', $bag->get('bar'), '->escapeValue() escapes % by doubling it');
        $this->assertEquals(array('bar' => array('ding' => 'I\'m a bar %%foo %%bar', 'zero' => null)), $bag->get('foo'), '->escapeValue() escapes % by doubling it');
    }

    /**
     * @dataProvider stringsWithSpacesProvider
     * @group legacy
     */
    public function testResolveStringWithSpacesReturnsString($expected, $test, $description)
    {
        $bag = new ParameterBag(array('foo' => 'bar'));

        try {
            $this->assertEquals($expected, $bag->resolveString($test), $description);
        } catch (ParameterNotFoundException $e) {
            $this->fail(sprintf('%s - "%s"', $description, $expected));
        }
    }

    public function stringsWithSpacesProvider()
    {
        return array(
            array('bar', '%foo%', 'Parameters must be wrapped by %.'),
            array('% foo %', '% foo %', 'Parameters should not have spaces.'),
            array('{% set my_template = "foo" %}', '{% set my_template = "foo" %}', 'Twig-like strings are not parameters.'),
            array('50% is less than 100%', '50% is less than 100%', 'Text between % signs is allowed, if there are spaces.'),
        );
    }
}
