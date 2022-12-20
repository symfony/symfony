<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\ParameterBag;

class ParameterBagTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testConstructor()
    {
        $this->testAll();
    }

    public function testAll()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        self::assertEquals(['foo' => 'bar'], $bag->all(), '->all() gets all the input');
    }

    public function testAllWithInputKey()
    {
        $bag = new ParameterBag(['foo' => ['bar', 'baz'], 'null' => null]);

        self::assertEquals(['bar', 'baz'], $bag->all('foo'), '->all() gets the value of a parameter');
        self::assertEquals([], $bag->all('unknown'), '->all() returns an empty array if a parameter is not defined');
    }

    public function testAllThrowsForNonArrayValues()
    {
        self::expectException(BadRequestException::class);
        $bag = new ParameterBag(['foo' => 'bar', 'null' => null]);
        $bag->all('foo');
    }

    public function testKeys()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        self::assertEquals(['foo'], $bag->keys());
    }

    public function testAdd()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $bag->add(['bar' => 'bas']);
        self::assertEquals(['foo' => 'bar', 'bar' => 'bas'], $bag->all());
    }

    public function testRemove()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $bag->add(['bar' => 'bas']);
        self::assertEquals(['foo' => 'bar', 'bar' => 'bas'], $bag->all());
        $bag->remove('bar');
        self::assertEquals(['foo' => 'bar'], $bag->all());
    }

    public function testReplace()
    {
        $bag = new ParameterBag(['foo' => 'bar']);

        $bag->replace(['FOO' => 'BAR']);
        self::assertEquals(['FOO' => 'BAR'], $bag->all(), '->replace() replaces the input with the argument');
        self::assertFalse($bag->has('foo'), '->replace() overrides previously set the input');
    }

    public function testGet()
    {
        $bag = new ParameterBag(['foo' => 'bar', 'null' => null]);

        self::assertEquals('bar', $bag->get('foo'), '->get() gets the value of a parameter');
        self::assertEquals('default', $bag->get('unknown', 'default'), '->get() returns second argument as default if a parameter is not defined');
        self::assertNull($bag->get('null', 'default'), '->get() returns null if null is set');
    }

    public function testGetDoesNotUseDeepByDefault()
    {
        $bag = new ParameterBag(['foo' => ['bar' => 'moo']]);

        self::assertNull($bag->get('foo[bar]'));
    }

    public function testSet()
    {
        $bag = new ParameterBag([]);

        $bag->set('foo', 'bar');
        self::assertEquals('bar', $bag->get('foo'), '->set() sets the value of parameter');

        $bag->set('foo', 'baz');
        self::assertEquals('baz', $bag->get('foo'), '->set() overrides previously set parameter');
    }

    public function testHas()
    {
        $bag = new ParameterBag(['foo' => 'bar']);

        self::assertTrue($bag->has('foo'), '->has() returns true if a parameter is defined');
        self::assertFalse($bag->has('unknown'), '->has() return false if a parameter is not defined');
    }

    public function testGetAlpha()
    {
        $bag = new ParameterBag(['word' => 'foo_BAR_012']);

        self::assertEquals('fooBAR', $bag->getAlpha('word'), '->getAlpha() gets only alphabetic characters');
        self::assertEquals('', $bag->getAlpha('unknown'), '->getAlpha() returns empty string if a parameter is not defined');
    }

    public function testGetAlnum()
    {
        $bag = new ParameterBag(['word' => 'foo_BAR_012']);

        self::assertEquals('fooBAR012', $bag->getAlnum('word'), '->getAlnum() gets only alphanumeric characters');
        self::assertEquals('', $bag->getAlnum('unknown'), '->getAlnum() returns empty string if a parameter is not defined');
    }

    public function testGetDigits()
    {
        $bag = new ParameterBag(['word' => 'foo_BAR_012']);

        self::assertEquals('012', $bag->getDigits('word'), '->getDigits() gets only digits as string');
        self::assertEquals('', $bag->getDigits('unknown'), '->getDigits() returns empty string if a parameter is not defined');
    }

    public function testGetInt()
    {
        $bag = new ParameterBag(['digits' => '0123']);

        self::assertEquals(123, $bag->getInt('digits'), '->getInt() gets a value of parameter as integer');
        self::assertEquals(0, $bag->getInt('unknown'), '->getInt() returns zero if a parameter is not defined');
    }

    public function testFilter()
    {
        $bag = new ParameterBag([
            'digits' => '0123ab',
            'email' => 'example@example.com',
            'url' => 'http://example.com/foo',
            'dec' => '256',
            'hex' => '0x100',
            'array' => ['bang'],
            ]);

        self::assertEmpty($bag->filter('nokey'), '->filter() should return empty by default if no key is found');

        self::assertEquals('0123', $bag->filter('digits', '', \FILTER_SANITIZE_NUMBER_INT), '->filter() gets a value of parameter as integer filtering out invalid characters');

        self::assertEquals('example@example.com', $bag->filter('email', '', \FILTER_VALIDATE_EMAIL), '->filter() gets a value of parameter as email');

        self::assertEquals('http://example.com/foo', $bag->filter('url', '', \FILTER_VALIDATE_URL, ['flags' => \FILTER_FLAG_PATH_REQUIRED]), '->filter() gets a value of parameter as URL with a path');

        // This test is repeated for code-coverage
        self::assertEquals('http://example.com/foo', $bag->filter('url', '', \FILTER_VALIDATE_URL, \FILTER_FLAG_PATH_REQUIRED), '->filter() gets a value of parameter as URL with a path');

        self::assertFalse($bag->filter('dec', '', \FILTER_VALIDATE_INT, [
            'flags' => \FILTER_FLAG_ALLOW_HEX,
            'options' => ['min_range' => 1, 'max_range' => 0xFF],
        ]), '->filter() gets a value of parameter as integer between boundaries');

        self::assertFalse($bag->filter('hex', '', \FILTER_VALIDATE_INT, [
            'flags' => \FILTER_FLAG_ALLOW_HEX,
            'options' => ['min_range' => 1, 'max_range' => 0xFF],
        ]), '->filter() gets a value of parameter as integer between boundaries');

        self::assertEquals(['bang'], $bag->filter('array', ''), '->filter() gets a value of parameter as an array');
    }

    /**
     * @group legacy
     */
    public function testFilterCallback()
    {
        $bag = new ParameterBag(['foo' => 'bar']);

        $this->expectDeprecation('Since symfony/http-foundation 5.2: Not passing a Closure together with FILTER_CALLBACK to "Symfony\Component\HttpFoundation\ParameterBag::filter()" is deprecated. Wrap your filter in a closure instead.');
        self::assertSame('BAR', $bag->filter('foo', null, \FILTER_CALLBACK, ['options' => 'strtoupper']));
    }

    public function testGetIterator()
    {
        $parameters = ['foo' => 'bar', 'hello' => 'world'];
        $bag = new ParameterBag($parameters);

        $i = 0;
        foreach ($bag as $key => $val) {
            ++$i;
            self::assertEquals($parameters[$key], $val);
        }

        self::assertEquals(\count($parameters), $i);
    }

    public function testCount()
    {
        $parameters = ['foo' => 'bar', 'hello' => 'world'];
        $bag = new ParameterBag($parameters);

        self::assertCount(\count($parameters), $bag);
    }

    public function testGetBoolean()
    {
        $parameters = ['string_true' => 'true', 'string_false' => 'false'];
        $bag = new ParameterBag($parameters);

        self::assertTrue($bag->getBoolean('string_true'), '->getBoolean() gets the string true as boolean true');
        self::assertFalse($bag->getBoolean('string_false'), '->getBoolean() gets the string false as boolean false');
        self::assertFalse($bag->getBoolean('unknown'), '->getBoolean() returns false if a parameter is not defined');
    }
}
