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
use Symfony\Component\DependencyInjection\Exception\ParameterCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class ParameterBagTest extends TestCase
{
    public function testConstructor()
    {
        $bag = new ParameterBag($parameters = [
            'foo' => 'foo',
            'bar' => 'bar',
        ]);
        self::assertEquals($parameters, $bag->all(), '__construct() takes an array of parameters as its first argument');
    }

    public function testClear()
    {
        $bag = new ParameterBag($parameters = [
            'foo' => 'foo',
            'bar' => 'bar',
        ]);
        $bag->clear();
        self::assertEquals([], $bag->all(), '->clear() removes all parameters');
    }

    public function testRemove()
    {
        $bag = new ParameterBag([
            'foo' => 'foo',
            'bar' => 'bar',
        ]);
        $bag->remove('foo');
        self::assertEquals(['bar' => 'bar'], $bag->all(), '->remove() removes a parameter');
    }

    public function testGetSet()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $bag->set('bar', 'foo');
        self::assertEquals('foo', $bag->get('bar'), '->set() sets the value of a new parameter');

        $bag->set('foo', 'baz');
        self::assertEquals('baz', $bag->get('foo'), '->set() overrides previously set parameter');

        try {
            $bag->get('baba');
            self::fail('->get() throws an Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException if the key does not exist');
        } catch (\Exception $e) {
            self::assertInstanceOf(ParameterNotFoundException::class, $e, '->get() throws an Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException if the key does not exist');
            self::assertEquals('You have requested a non-existent parameter "baba".', $e->getMessage(), '->get() throws an Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException if the key does not exist');
        }
    }

    /**
     * @dataProvider provideGetThrowParameterNotFoundExceptionData
     */
    public function testGetThrowParameterNotFoundException($parameterKey, $exceptionMessage)
    {
        $bag = new ParameterBag([
            'foo' => 'foo',
            'bar' => 'bar',
            'baz' => 'baz',
            'fiz' => ['bar' => ['boo' => 12]],
        ]);

        self::expectException(ParameterNotFoundException::class);
        self::expectExceptionMessage($exceptionMessage);

        $bag->get($parameterKey);
    }

    public function provideGetThrowParameterNotFoundExceptionData()
    {
        return [
            ['foo1', 'You have requested a non-existent parameter "foo1". Did you mean this: "foo"?'],
            ['bag', 'You have requested a non-existent parameter "bag". Did you mean one of these: "bar", "baz"?'],
            ['', 'You have requested a non-existent parameter "".'],

            ['fiz.bar.boo', 'You have requested a non-existent parameter "fiz.bar.boo". You cannot access nested array items, do you want to inject "fiz" instead?'],
        ];
    }

    public function testHas()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        self::assertTrue($bag->has('foo'), '->has() returns true if a parameter is defined');
        self::assertFalse($bag->has('bar'), '->has() returns false if a parameter is not defined');
    }

    public function testMixedCase()
    {
        $bag = new ParameterBag([
            'foo' => 'foo',
            'bar' => 'bar',
            'BAR' => 'baz',
        ]);

        $bag->remove('BAR');
        self::assertEquals(['foo' => 'foo', 'bar' => 'bar'], $bag->all());

        $bag->set('Foo', 'baz1');
        self::assertEquals('foo', $bag->get('foo'));
        self::assertEquals('baz1', $bag->get('Foo'));
    }

    public function testResolveValue()
    {
        $bag = new ParameterBag([]);
        self::assertEquals('foo', $bag->resolveValue('foo'), '->resolveValue() returns its argument unmodified if no placeholders are found');

        $bag = new ParameterBag(['foo' => 'bar']);
        self::assertEquals('I\'m a bar', $bag->resolveValue('I\'m a %foo%'), '->resolveValue() replaces placeholders by their values');
        self::assertEquals(['bar' => 'bar'], $bag->resolveValue(['%foo%' => '%foo%']), '->resolveValue() replaces placeholders in keys and values of arrays');
        self::assertEquals(['bar' => ['bar' => ['bar' => 'bar']]], $bag->resolveValue(['%foo%' => ['%foo%' => ['%foo%' => '%foo%']]]), '->resolveValue() replaces placeholders in nested arrays');
        self::assertEquals('I\'m a %%foo%%', $bag->resolveValue('I\'m a %%foo%%'), '->resolveValue() supports % escaping by doubling it');
        self::assertEquals('I\'m a bar %%foo bar', $bag->resolveValue('I\'m a %foo% %%foo %foo%'), '->resolveValue() supports % escaping by doubling it');
        self::assertEquals(['foo' => ['bar' => ['ding' => 'I\'m a bar %%foo %%bar']]], $bag->resolveValue(['foo' => ['bar' => ['ding' => 'I\'m a bar %%foo %%bar']]]), '->resolveValue() supports % escaping by doubling it');

        $bag = new ParameterBag(['foo' => true]);
        self::assertTrue($bag->resolveValue('%foo%'), '->resolveValue() replaces arguments that are just a placeholder by their value without casting them to strings');
        $bag = new ParameterBag(['foo' => null]);
        self::assertNull($bag->resolveValue('%foo%'), '->resolveValue() replaces arguments that are just a placeholder by their value without casting them to strings');

        $bag = new ParameterBag(['foo' => 'bar', 'baz' => '%%%foo% %foo%%% %%foo%% %%%foo%%%']);
        self::assertEquals('%%bar bar%% %%foo%% %%bar%%', $bag->resolveValue('%baz%'), '->resolveValue() replaces params placed besides escaped %');

        $bag = new ParameterBag(['baz' => '%%s?%%s']);
        self::assertEquals('%%s?%%s', $bag->resolveValue('%baz%'), '->resolveValue() is not replacing greedily');

        $bag = new ParameterBag([]);
        try {
            $bag->resolveValue('%foobar%');
            self::fail('->resolveValue() throws an InvalidArgumentException if a placeholder references a non-existent parameter');
        } catch (ParameterNotFoundException $e) {
            self::assertEquals('You have requested a non-existent parameter "foobar".', $e->getMessage(), '->resolveValue() throws a ParameterNotFoundException if a placeholder references a non-existent parameter');
        }

        try {
            $bag->resolveValue('foo %foobar% bar');
            self::fail('->resolveValue() throws a ParameterNotFoundException if a placeholder references a non-existent parameter');
        } catch (ParameterNotFoundException $e) {
            self::assertEquals('You have requested a non-existent parameter "foobar".', $e->getMessage(), '->resolveValue() throws a ParameterNotFoundException if a placeholder references a non-existent parameter');
        }

        $bag = new ParameterBag(['foo' => 'a %bar%', 'bar' => []]);
        try {
            $bag->resolveValue('%foo%');
            self::fail('->resolveValue() throws a RuntimeException when a parameter embeds another non-string parameter');
        } catch (RuntimeException $e) {
            self::assertEquals('A string value must be composed of strings and/or numbers, but found parameter "bar" of type "array" inside string value "a %bar%".', $e->getMessage(), '->resolveValue() throws a RuntimeException when a parameter embeds another non-string parameter');
        }

        $bag = new ParameterBag(['foo' => '%bar%', 'bar' => '%foobar%', 'foobar' => '%foo%']);
        try {
            $bag->resolveValue('%foo%');
            self::fail('->resolveValue() throws a ParameterCircularReferenceException when a parameter has a circular reference');
        } catch (ParameterCircularReferenceException $e) {
            self::assertEquals('Circular reference detected for parameter "foo" ("foo" > "bar" > "foobar" > "foo").', $e->getMessage(), '->resolveValue() throws a ParameterCircularReferenceException when a parameter has a circular reference');
        }

        $bag = new ParameterBag(['foo' => 'a %bar%', 'bar' => 'a %foobar%', 'foobar' => 'a %foo%']);
        try {
            $bag->resolveValue('%foo%');
            self::fail('->resolveValue() throws a ParameterCircularReferenceException when a parameter has a circular reference');
        } catch (ParameterCircularReferenceException $e) {
            self::assertEquals('Circular reference detected for parameter "foo" ("foo" > "bar" > "foobar" > "foo").', $e->getMessage(), '->resolveValue() throws a ParameterCircularReferenceException when a parameter has a circular reference');
        }

        $bag = new ParameterBag(['host' => 'foo.bar', 'port' => 1337]);
        self::assertEquals('foo.bar:1337', $bag->resolveValue('%host%:%port%'));
    }

    public function testResolveIndicatesWhyAParameterIsNeeded()
    {
        $bag = new ParameterBag(['foo' => '%bar%']);

        try {
            $bag->resolve();
        } catch (ParameterNotFoundException $e) {
            self::assertEquals('The parameter "foo" has a dependency on a non-existent parameter "bar".', $e->getMessage());
        }

        $bag = new ParameterBag(['foo' => '%bar%']);

        try {
            $bag->resolve();
        } catch (ParameterNotFoundException $e) {
            self::assertEquals('The parameter "foo" has a dependency on a non-existent parameter "bar".', $e->getMessage());
        }
    }

    public function testResolveUnescapesValue()
    {
        $bag = new ParameterBag([
            'foo' => ['bar' => ['ding' => 'I\'m a bar %%foo %%bar']],
            'bar' => 'I\'m a %%foo%%',
        ]);

        $bag->resolve();

        self::assertEquals('I\'m a %foo%', $bag->get('bar'), '->resolveValue() supports % escaping by doubling it');
        self::assertEquals(['bar' => ['ding' => 'I\'m a bar %foo %bar']], $bag->get('foo'), '->resolveValue() supports % escaping by doubling it');
    }

    public function testEscapeValue()
    {
        $bag = new ParameterBag();

        $bag->add([
            'foo' => $bag->escapeValue(['bar' => ['ding' => 'I\'m a bar %foo %bar', 'zero' => null]]),
            'bar' => $bag->escapeValue('I\'m a %foo%'),
        ]);

        self::assertEquals('I\'m a %%foo%%', $bag->get('bar'), '->escapeValue() escapes % by doubling it');
        self::assertEquals(['bar' => ['ding' => 'I\'m a bar %%foo %%bar', 'zero' => null]], $bag->get('foo'), '->escapeValue() escapes % by doubling it');
    }

    /**
     * @dataProvider stringsWithSpacesProvider
     */
    public function testResolveStringWithSpacesReturnsString($expected, $test, $description)
    {
        $bag = new ParameterBag(['foo' => 'bar']);

        try {
            self::assertEquals($expected, $bag->resolveString($test), $description);
        } catch (ParameterNotFoundException $e) {
            self::fail(sprintf('%s - "%s"', $description, $expected));
        }
    }

    public function stringsWithSpacesProvider()
    {
        return [
            ['bar', '%foo%', 'Parameters must be wrapped by %.'],
            ['% foo %', '% foo %', 'Parameters should not have spaces.'],
            ['{% set my_template = "foo" %}', '{% set my_template = "foo" %}', 'Twig-like strings are not parameters.'],
            ['50% is less than 100%', '50% is less than 100%', 'Text between % signs is allowed, if there are spaces.'],
        ];
    }
}
