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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\ParameterCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

class ParameterBagTest extends TestCase
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
    }

    public function testGetSet()
    {
        $bag = new ParameterBag(array('foo' => 'bar'));
        $bag->set('bar', 'foo');
        $this->assertEquals('foo', $bag->get('bar'), '->set() sets the value of a new parameter');

        $bag->set('foo', 'baz');
        $this->assertEquals('baz', $bag->get('foo'), '->set() overrides previously set parameter');

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

        if (method_exists($this, 'expectException')) {
            $this->expectException(ParameterNotFoundException::class);
            $this->expectExceptionMessage($exceptionMessage);
        } else {
            $this->setExpectedException(ParameterNotFoundException::class, $exceptionMessage);
        }

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
        $this->assertFalse($bag->has('bar'), '->has() returns false if a parameter is not defined');
    }

    public function testMixedCase()
    {
        $bag = new ParameterBag(array(
            'foo' => 'foo',
            'bar' => 'bar',
            'BAR' => 'baz',
        ));

        $bag->remove('BAR');
        $this->assertEquals(array('foo' => 'foo', 'bar' => 'bar'), $bag->all());

        $bag->set('Foo', 'baz1');
        $this->assertEquals('foo', $bag->get('foo'));
        $this->assertEquals('baz1', $bag->get('Foo'));
    }

    public function testResolveValue()
    {
        $bag = new ParameterBag(array());
        $this->assertEquals('foo', $bag->resolveValue('foo'), '->resolveValue() returns its argument unmodified if no placeholders are found');

        $bag = new ParameterBag(array('foo' => 'bar'));
        $this->assertEquals('I\'m a bar', $bag->resolveValue('I\'m a %foo%'), '->resolveValue() replaces placeholders by their values');
        $this->assertEquals(array('bar' => 'bar'), $bag->resolveValue(array('%foo%' => '%foo%')), '->resolveValue() replaces placeholders in keys and values of arrays');
        $this->assertEquals(array('bar' => array('bar' => array('bar' => 'bar'))), $bag->resolveValue(array('%foo%' => array('%foo%' => array('%foo%' => '%foo%')))), '->resolveValue() replaces placeholders in nested arrays');
        $this->assertEquals('I\'m a %%foo%%', $bag->resolveValue('I\'m a %%foo%%'), '->resolveValue() supports % escaping by doubling it');
        $this->assertEquals('I\'m a bar %%foo bar', $bag->resolveValue('I\'m a %foo% %%foo %foo%'), '->resolveValue() supports % escaping by doubling it');
        $this->assertEquals(array('foo' => array('bar' => array('ding' => 'I\'m a bar %%foo %%bar'))), $bag->resolveValue(array('foo' => array('bar' => array('ding' => 'I\'m a bar %%foo %%bar')))), '->resolveValue() supports % escaping by doubling it');

        $bag = new ParameterBag(array('foo' => true));
        $this->assertTrue($bag->resolveValue('%foo%'), '->resolveValue() replaces arguments that are just a placeholder by their value without casting them to strings');
        $bag = new ParameterBag(array('foo' => null));
        $this->assertNull($bag->resolveValue('%foo%'), '->resolveValue() replaces arguments that are just a placeholder by their value without casting them to strings');

        $bag = new ParameterBag(array('foo' => 'bar', 'baz' => '%%%foo% %foo%%% %%foo%% %%%foo%%%'));
        $this->assertEquals('%%bar bar%% %%foo%% %%bar%%', $bag->resolveValue('%baz%'), '->resolveValue() replaces params placed besides escaped %');

        $bag = new ParameterBag(array('baz' => '%%s?%%s'));
        $this->assertEquals('%%s?%%s', $bag->resolveValue('%baz%'), '->resolveValue() is not replacing greedily');

        $bag = new ParameterBag(array());
        try {
            $bag->resolveValue('%foobar%');
            $this->fail('->resolveValue() throws an InvalidArgumentException if a placeholder references a non-existent parameter');
        } catch (ParameterNotFoundException $e) {
            $this->assertEquals('You have requested a non-existent parameter "foobar".', $e->getMessage(), '->resolveValue() throws a ParameterNotFoundException if a placeholder references a non-existent parameter');
        }

        try {
            $bag->resolveValue('foo %foobar% bar');
            $this->fail('->resolveValue() throws a ParameterNotFoundException if a placeholder references a non-existent parameter');
        } catch (ParameterNotFoundException $e) {
            $this->assertEquals('You have requested a non-existent parameter "foobar".', $e->getMessage(), '->resolveValue() throws a ParameterNotFoundException if a placeholder references a non-existent parameter');
        }

        $bag = new ParameterBag(array('foo' => 'a %bar%', 'bar' => array()));
        try {
            $bag->resolveValue('%foo%');
            $this->fail('->resolveValue() throws a RuntimeException when a parameter embeds another non-string parameter');
        } catch (RuntimeException $e) {
            $this->assertEquals('A string value must be composed of strings and/or numbers, but found parameter "bar" of type array inside string value "a %bar%".', $e->getMessage(), '->resolveValue() throws a RuntimeException when a parameter embeds another non-string parameter');
        }

        $bag = new ParameterBag(array('foo' => '%bar%', 'bar' => '%foobar%', 'foobar' => '%foo%'));
        try {
            $bag->resolveValue('%foo%');
            $this->fail('->resolveValue() throws a ParameterCircularReferenceException when a parameter has a circular reference');
        } catch (ParameterCircularReferenceException $e) {
            $this->assertEquals('Circular reference detected for parameter "foo" ("foo" > "bar" > "foobar" > "foo").', $e->getMessage(), '->resolveValue() throws a ParameterCircularReferenceException when a parameter has a circular reference');
        }

        $bag = new ParameterBag(array('foo' => 'a %bar%', 'bar' => 'a %foobar%', 'foobar' => 'a %foo%'));
        try {
            $bag->resolveValue('%foo%');
            $this->fail('->resolveValue() throws a ParameterCircularReferenceException when a parameter has a circular reference');
        } catch (ParameterCircularReferenceException $e) {
            $this->assertEquals('Circular reference detected for parameter "foo" ("foo" > "bar" > "foobar" > "foo").', $e->getMessage(), '->resolveValue() throws a ParameterCircularReferenceException when a parameter has a circular reference');
        }

        $bag = new ParameterBag(array('host' => 'foo.bar', 'port' => 1337));
        $this->assertEquals('foo.bar:1337', $bag->resolveValue('%host%:%port%'));
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
