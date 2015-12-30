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
use Symfony\Component\DependencyInjection\Exception\ParameterCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

class ParameterResolverTraitTest extends \PHPUnit_Framework_TestCase
{
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
}
