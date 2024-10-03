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
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\DependencyInjection\Exception\EmptyParameterValueException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ParameterCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class ParameterBagTest extends TestCase
{
    use ExpectUserDeprecationMessageTrait;

    public function testConstructor()
    {
        $bag = new ParameterBag($parameters = [
            'foo' => 'foo',
            'bar' => 'bar',
        ]);
        $this->assertEquals($parameters, $bag->all(), '__construct() takes an array of parameters as its first argument');
    }

    public function testClear()
    {
        $bag = new ParameterBag($parameters = [
            'foo' => 'foo',
            'bar' => 'bar',
        ]);
        $bag->clear();
        $this->assertEquals([], $bag->all(), '->clear() removes all parameters');
    }

    public function testRemove()
    {
        $bag = new ParameterBag([
            'foo' => 'foo',
            'bar' => 'bar',
        ]);
        $bag->remove('foo');
        $this->assertEquals(['bar' => 'bar'], $bag->all(), '->remove() removes a parameter');
    }

    public function testRemoveWithDeprecation()
    {
        $bag = new ParameterBag([
            'foo' => 'foo',
            'bar' => 'bar',
        ]);
        $bag->deprecate('foo', 'symfony/test', '6.3');
        $bag->remove('foo');
        $this->assertEquals(['bar' => 'bar'], $bag->all(), '->remove() removes a parameter');
        $this->assertEquals([], $bag->allDeprecated());
    }

    public function testGetSet()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $bag->set('bar', 'foo');
        $this->assertEquals('foo', $bag->get('bar'), '->set() sets the value of a new parameter');

        $bag->set('foo', 'baz');
        $this->assertEquals('baz', $bag->get('foo'), '->set() overrides previously set parameter');

        try {
            $bag->get('baba');
            $this->fail('->get() throws an Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException if the key does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf(ParameterNotFoundException::class, $e, '->get() throws an Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException if the key does not exist');
            $this->assertEquals('You have requested a non-existent parameter "baba".', $e->getMessage(), '->get() throws an Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException if the key does not exist');
        }
    }

    /**
     * @testWith [1001]
     *           [10.0]
     */
    public function testSetNumericName(int|float $name)
    {
        $bag = new ParameterBag();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The parameter name "%s" cannot be numeric.', $name));

        $bag->set($name, 'foo');
    }

    /**
     * @testWith [1001]
     *           [10.0]
     */
    public function testConstructorNumericName(int|float $name)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The parameter name "%s" cannot be numeric.', $name));

        new ParameterBag([$name => 'foo']);
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

        $this->expectException(ParameterNotFoundException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $bag->get($parameterKey);
    }

    public static function provideGetThrowParameterNotFoundExceptionData()
    {
        return [
            ['foo1', 'You have requested a non-existent parameter "foo1". Did you mean this: "foo"?'],
            ['bag', 'You have requested a non-existent parameter "bag". Did you mean one of these: "bar", "baz"?'],
            ['', 'You have requested a non-existent parameter "".'],

            ['fiz.bar.boo', 'You have requested a non-existent parameter "fiz.bar.boo". You cannot access nested array items, do you want to inject "fiz" instead?'],
            ['.foo', 'Parameter ".foo" not found. It was probably deleted during the compilation of the container. Did you mean this: "foo"?'],
        ];
    }

    /**
     * The test should be kept in the group as it always expects a deprecation.
     *
     * @group legacy
     */
    public function testDeprecate()
    {
        $bag = new ParameterBag(['foo' => 'bar']);

        $bag->deprecate('foo', 'symfony/test', '6.3');

        $this->expectUserDeprecationMessage('Since symfony/test 6.3: The parameter "foo" is deprecated.');

        $bag->get('foo');
    }

    /**
     * The test should be kept in the group as it always expects a deprecation.
     *
     * @group legacy
     */
    public function testDeprecateWithMessage()
    {
        $bag = new ParameterBag(['foo' => 'bar']);

        $bag->deprecate('foo', 'symfony/test', '6.3', 'The parameter "%s" is deprecated, use "new_foo" instead.');

        $this->expectUserDeprecationMessage('Since symfony/test 6.3: The parameter "foo" is deprecated, use "new_foo" instead.');

        $bag->get('foo');
    }

    /**
     * The test should be kept in the group as it always expects a deprecation.
     *
     * @group legacy
     */
    public function testDeprecationIsTriggeredWhenResolved()
    {
        $bag = new ParameterBag(['foo' => '%bar%', 'bar' => 'baz']);

        $bag->deprecate('bar', 'symfony/test', '6.3');

        $this->expectUserDeprecationMessage('Since symfony/test 6.3: The parameter "bar" is deprecated.');

        $bag->resolve();
    }

    public function testDeprecateThrowsWhenParameterIsUndefined()
    {
        $bag = new ParameterBag();

        $this->expectException(ParameterNotFoundException::class);
        $this->expectExceptionMessage('You have requested a non-existent parameter "foo".');

        $bag->deprecate('foo', 'symfony/test', '6.3');
    }

    public function testGetMissingRequiredParameter()
    {
        $bag = new ParameterBag();

        $bag->cannotBeEmpty('bar', 'Did you forget to configure the "foo.bar" option?');

        $this->expectException(ParameterNotFoundException::class);
        $this->expectExceptionMessage('You have requested a non-existent parameter "bar". Did you forget to configure the "foo.bar" option?');

        $bag->get('bar');
    }

    public function testGetNonEmptyParameterThrowsWhenNullValue()
    {
        $bag = new ParameterBag();
        $bag->set('bar', null);
        $bag->cannotBeEmpty('bar', 'Did you forget to configure the "foo.bar" option?');

        $this->expectException(EmptyParameterValueException::class);
        $this->expectExceptionMessage('Did you forget to configure the "foo.bar" option?');

        $bag->get('bar');
    }

    public function testGetNonEmptyParameterThrowsWhenEmptyStringValue()
    {
        $bag = new ParameterBag();
        $bag->set('bar', '');
        $bag->cannotBeEmpty('bar', 'Did you forget to configure the "foo.bar" option?');

        $this->expectException(EmptyParameterValueException::class);
        $this->expectExceptionMessage('Did you forget to configure the "foo.bar" option?');

        $bag->get('bar');
    }

    public function testGetNonEmptyParameterThrowsWhenEmptyArrayValue()
    {
        $bag = new ParameterBag();
        $bag->set('bar', []);
        $bag->cannotBeEmpty('bar', 'Did you forget to configure the "foo.bar" option?');

        $this->expectException(EmptyParameterValueException::class);
        $this->expectExceptionMessage('Did you forget to configure the "foo.bar" option?');

        $bag->get('bar');
    }

    public function testHas()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $this->assertTrue($bag->has('foo'), '->has() returns true if a parameter is defined');
        $this->assertFalse($bag->has('bar'), '->has() returns false if a parameter is not defined');
    }

    public function testMixedCase()
    {
        $bag = new ParameterBag([
            'foo' => 'foo',
            'bar' => 'bar',
            'BAR' => 'baz',
        ]);

        $bag->remove('BAR');
        $this->assertEquals(['foo' => 'foo', 'bar' => 'bar'], $bag->all());

        $bag->set('Foo', 'baz1');
        $this->assertEquals('foo', $bag->get('foo'));
        $this->assertEquals('baz1', $bag->get('Foo'));
    }

    public function testResolveValue()
    {
        $bag = new ParameterBag([]);
        $this->assertEquals('foo', $bag->resolveValue('foo'), '->resolveValue() returns its argument unmodified if no placeholders are found');

        $bag = new ParameterBag(['foo' => 'bar']);
        $this->assertEquals('I\'m a bar', $bag->resolveValue('I\'m a %foo%'), '->resolveValue() replaces placeholders by their values');
        $this->assertEquals(['bar' => 'bar'], $bag->resolveValue(['%foo%' => '%foo%']), '->resolveValue() replaces placeholders in keys and values of arrays');
        $this->assertEquals(['bar' => ['bar' => ['bar' => 'bar']]], $bag->resolveValue(['%foo%' => ['%foo%' => ['%foo%' => '%foo%']]]), '->resolveValue() replaces placeholders in nested arrays');
        $this->assertEquals('I\'m a %%foo%%', $bag->resolveValue('I\'m a %%foo%%'), '->resolveValue() supports % escaping by doubling it');
        $this->assertEquals('I\'m a bar %%foo bar', $bag->resolveValue('I\'m a %foo% %%foo %foo%'), '->resolveValue() supports % escaping by doubling it');
        $this->assertEquals(['foo' => ['bar' => ['ding' => 'I\'m a bar %%foo %%bar']]], $bag->resolveValue(['foo' => ['bar' => ['ding' => 'I\'m a bar %%foo %%bar']]]), '->resolveValue() supports % escaping by doubling it');

        $bag = new ParameterBag(['foo' => true]);
        $this->assertTrue($bag->resolveValue('%foo%'), '->resolveValue() replaces arguments that are just a placeholder by their value without casting them to strings');
        $bag = new ParameterBag(['foo' => null]);
        $this->assertNull($bag->resolveValue('%foo%'), '->resolveValue() replaces arguments that are just a placeholder by their value without casting them to strings');

        $bag = new ParameterBag(['foo' => 'bar', 'baz' => '%%%foo% %foo%%% %%foo%% %%%foo%%%']);
        $this->assertEquals('%%bar bar%% %%foo%% %%bar%%', $bag->resolveValue('%baz%'), '->resolveValue() replaces params placed besides escaped %');

        $bag = new ParameterBag(['baz' => '%%s?%%s']);
        $this->assertEquals('%%s?%%s', $bag->resolveValue('%baz%'), '->resolveValue() is not replacing greedily');

        $bag = new ParameterBag([]);
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

        $bag = new ParameterBag(['foo' => 'a %bar%', 'bar' => []]);
        try {
            $bag->resolveValue('%foo%');
            $this->fail('->resolveValue() throws a RuntimeException when a parameter embeds another non-string parameter');
        } catch (RuntimeException $e) {
            $this->assertEquals('A string value must be composed of strings and/or numbers, but found parameter "bar" of type "array" inside string value "a %bar%".', $e->getMessage(), '->resolveValue() throws a RuntimeException when a parameter embeds another non-string parameter');
        }

        $bag = new ParameterBag(['foo' => '%bar%', 'bar' => '%foobar%', 'foobar' => '%foo%']);
        try {
            $bag->resolveValue('%foo%');
            $this->fail('->resolveValue() throws a ParameterCircularReferenceException when a parameter has a circular reference');
        } catch (ParameterCircularReferenceException $e) {
            $this->assertEquals('Circular reference detected for parameter "foo" ("foo" > "bar" > "foobar" > "foo").', $e->getMessage(), '->resolveValue() throws a ParameterCircularReferenceException when a parameter has a circular reference');
        }

        $bag = new ParameterBag(['foo' => 'a %bar%', 'bar' => 'a %foobar%', 'foobar' => 'a %foo%']);
        try {
            $bag->resolveValue('%foo%');
            $this->fail('->resolveValue() throws a ParameterCircularReferenceException when a parameter has a circular reference');
        } catch (ParameterCircularReferenceException $e) {
            $this->assertEquals('Circular reference detected for parameter "foo" ("foo" > "bar" > "foobar" > "foo").', $e->getMessage(), '->resolveValue() throws a ParameterCircularReferenceException when a parameter has a circular reference');
        }

        $bag = new ParameterBag(['host' => 'foo.bar', 'port' => 1337]);
        $this->assertEquals('foo.bar:1337', $bag->resolveValue('%host%:%port%'));
    }

    public function testResolveIndicatesWhyAParameterIsNeeded()
    {
        $bag = new ParameterBag(['foo' => '%bar%']);

        try {
            $bag->resolve();
        } catch (ParameterNotFoundException $e) {
            $this->assertEquals('The parameter "foo" has a dependency on a non-existent parameter "bar".', $e->getMessage());
        }

        $bag = new ParameterBag(['foo' => '%bar%']);

        try {
            $bag->resolve();
        } catch (ParameterNotFoundException $e) {
            $this->assertEquals('The parameter "foo" has a dependency on a non-existent parameter "bar".', $e->getMessage());
        }
    }

    public function testResolveUnescapesValue()
    {
        $bag = new ParameterBag([
            'foo' => ['bar' => ['ding' => 'I\'m a bar %%foo %%bar']],
            'bar' => 'I\'m a %%foo%%',
        ]);

        $bag->resolve();

        $this->assertEquals('I\'m a %foo%', $bag->get('bar'), '->resolveValue() supports % escaping by doubling it');
        $this->assertEquals(['bar' => ['ding' => 'I\'m a bar %foo %bar']], $bag->get('foo'), '->resolveValue() supports % escaping by doubling it');
    }

    public function testEscapeValue()
    {
        $bag = new ParameterBag();

        $bag->add([
            'foo' => $bag->escapeValue(['bar' => ['ding' => 'I\'m a bar %foo %bar', 'zero' => null]]),
            'bar' => $bag->escapeValue('I\'m a %foo%'),
        ]);

        $this->assertEquals('I\'m a %%foo%%', $bag->get('bar'), '->escapeValue() escapes % by doubling it');
        $this->assertEquals(['bar' => ['ding' => 'I\'m a bar %%foo %%bar', 'zero' => null]], $bag->get('foo'), '->escapeValue() escapes % by doubling it');
    }

    /**
     * @dataProvider stringsWithSpacesProvider
     */
    public function testResolveStringWithSpacesReturnsString($expected, $test, $description)
    {
        $bag = new ParameterBag(['foo' => 'bar']);

        try {
            $this->assertEquals($expected, $bag->resolveString($test), $description);
        } catch (ParameterNotFoundException $e) {
            $this->fail(\sprintf('%s - "%s"', $description, $expected));
        }
    }

    public static function stringsWithSpacesProvider()
    {
        return [
            ['bar', '%foo%', 'Parameters must be wrapped by %.'],
            ['% foo %', '% foo %', 'Parameters should not have spaces.'],
            ['{% set my_template = "foo" %}', '{% set my_template = "foo" %}', 'Twig-like strings are not parameters.'],
            ['50% is less than 100%', '50% is less than 100%', 'Text between % signs is allowed, if there are spaces.'],
        ];
    }
}
