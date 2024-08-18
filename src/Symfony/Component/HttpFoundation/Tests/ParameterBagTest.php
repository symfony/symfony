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
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Exception\UnexpectedValueException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Tests\Fixtures\FooEnum;

class ParameterBagTest extends TestCase
{
    public function testConstructor()
    {
        $this->testAll();
    }

    public function testAll()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $this->assertEquals(['foo' => 'bar'], $bag->all(), '->all() gets all the input');
    }

    public function testAllWithInputKey()
    {
        $bag = new ParameterBag(['foo' => ['bar', 'baz'], 'null' => null]);

        $this->assertEquals(['bar', 'baz'], $bag->all('foo'), '->all() gets the value of a parameter');
        $this->assertEquals([], $bag->all('unknown'), '->all() returns an empty array if a parameter is not defined');
    }

    public function testAllThrowsForNonArrayValues()
    {
        $this->expectException(BadRequestException::class);
        $bag = new ParameterBag(['foo' => 'bar', 'null' => null]);
        $bag->all('foo');
    }

    public function testKeys()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $this->assertEquals(['foo'], $bag->keys());
    }

    public function testAdd()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $bag->add(['bar' => 'bas']);
        $this->assertEquals(['foo' => 'bar', 'bar' => 'bas'], $bag->all());
    }

    public function testRemove()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $bag->add(['bar' => 'bas']);
        $this->assertEquals(['foo' => 'bar', 'bar' => 'bas'], $bag->all());
        $bag->remove('bar');
        $this->assertEquals(['foo' => 'bar'], $bag->all());
    }

    public function testReplace()
    {
        $bag = new ParameterBag(['foo' => 'bar']);

        $bag->replace(['FOO' => 'BAR']);
        $this->assertEquals(['FOO' => 'BAR'], $bag->all(), '->replace() replaces the input with the argument');
        $this->assertFalse($bag->has('foo'), '->replace() overrides previously set the input');
    }

    public function testGet()
    {
        $bag = new ParameterBag(['foo' => 'bar', 'null' => null]);

        $this->assertEquals('bar', $bag->get('foo'), '->get() gets the value of a parameter');
        $this->assertEquals('default', $bag->get('unknown', 'default'), '->get() returns second argument as default if a parameter is not defined');
        $this->assertNull($bag->get('null', 'default'), '->get() returns null if null is set');
    }

    public function testGetDoesNotUseDeepByDefault()
    {
        $bag = new ParameterBag(['foo' => ['bar' => 'moo']]);

        $this->assertNull($bag->get('foo[bar]'));
    }

    public function testSet()
    {
        $bag = new ParameterBag([]);

        $bag->set('foo', 'bar');
        $this->assertEquals('bar', $bag->get('foo'), '->set() sets the value of parameter');

        $bag->set('foo', 'baz');
        $this->assertEquals('baz', $bag->get('foo'), '->set() overrides previously set parameter');
    }

    public function testHas()
    {
        $bag = new ParameterBag(['foo' => 'bar']);

        $this->assertTrue($bag->has('foo'), '->has() returns true if a parameter is defined');
        $this->assertFalse($bag->has('unknown'), '->has() return false if a parameter is not defined');
    }

    public function testGetAlpha()
    {
        $bag = new ParameterBag(['word' => 'foo_BAR_012', 'bool' => true, 'integer' => 123]);

        $this->assertSame('fooBAR', $bag->getAlpha('word'), '->getAlpha() gets only alphabetic characters');
        $this->assertSame('', $bag->getAlpha('unknown'), '->getAlpha() returns empty string if a parameter is not defined');
        $this->assertSame('abcDEF', $bag->getAlpha('unknown', 'abc_DEF_012'), '->getAlpha() returns filtered default if a parameter is not defined');
        $this->assertSame('', $bag->getAlpha('integer', 'abc_DEF_012'), '->getAlpha() returns empty string if a parameter is an integer');
        $this->assertSame('', $bag->getAlpha('bool', 'abc_DEF_012'), '->getAlpha() returns empty string if a parameter is a boolean');
    }

    public function testGetAlphaExceptionWithArray()
    {
        $bag = new ParameterBag(['word' => ['foo_BAR_012']]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Parameter value "word" cannot be converted to "string".');

        $bag->getAlpha('word');
    }

    public function testGetAlnum()
    {
        $bag = new ParameterBag(['word' => 'foo_BAR_012', 'bool' => true, 'integer' => 123]);

        $this->assertSame('fooBAR012', $bag->getAlnum('word'), '->getAlnum() gets only alphanumeric characters');
        $this->assertSame('', $bag->getAlnum('unknown'), '->getAlnum() returns empty string if a parameter is not defined');
        $this->assertSame('abcDEF012', $bag->getAlnum('unknown', 'abc_DEF_012'), '->getAlnum() returns filtered default if a parameter is not defined');
        $this->assertSame('123', $bag->getAlnum('integer', 'abc_DEF_012'), '->getAlnum() returns the number as string if a parameter is an integer');
        $this->assertSame('1', $bag->getAlnum('bool', 'abc_DEF_012'), '->getAlnum() returns 1 if a parameter is true');
    }

    public function testGetAlnumExceptionWithArray()
    {
        $bag = new ParameterBag(['word' => ['foo_BAR_012']]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Parameter value "word" cannot be converted to "string".');

        $bag->getAlnum('word');
    }

    public function testGetDigits()
    {
        $bag = new ParameterBag(['word' => 'foo_BAR_0+1-2', 'bool' => true, 'integer' => 123]);

        $this->assertSame('012', $bag->getDigits('word'), '->getDigits() gets only digits as string');
        $this->assertSame('', $bag->getDigits('unknown'), '->getDigits() returns empty string if a parameter is not defined');
        $this->assertSame('012', $bag->getDigits('unknown', 'abc_DEF_012'), '->getDigits() returns filtered default if a parameter is not defined');
        $this->assertSame('123', $bag->getDigits('integer', 'abc_DEF_012'), '->getDigits() returns the number as string if a parameter is an integer');
        $this->assertSame('1', $bag->getDigits('bool', 'abc_DEF_012'), '->getDigits() returns 1 if a parameter is true');
    }

    public function testGetDigitsExceptionWithArray()
    {
        $bag = new ParameterBag(['word' => ['foo_BAR_012']]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Parameter value "word" cannot be converted to "string".');

        $bag->getDigits('word');
    }

    public function testGetInt()
    {
        $bag = new ParameterBag(['digits' => '123', 'bool' => true]);

        $this->assertSame(123, $bag->getInt('digits', 0), '->getInt() gets a value of parameter as integer');
        $this->assertSame(0, $bag->getInt('unknown', 0), '->getInt() returns zero if a parameter is not defined');
        $this->assertSame(10, $bag->getInt('unknown', 10), '->getInt() returns the default if a parameter is not defined');
        $this->assertSame(1, $bag->getInt('bool', 0), '->getInt() returns 1 if a parameter is true');
    }

    public function testGetIntExceptionWithArray()
    {
        $bag = new ParameterBag(['digits' => ['123']]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Parameter value "digits" is invalid and flag "FILTER_NULL_ON_FAILURE" was not set.');

        $bag->getInt('digits');
    }

    public function testGetIntExceptionWithInvalid()
    {
        $bag = new ParameterBag(['word' => 'foo_BAR_012']);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Parameter value "word" is invalid and flag "FILTER_NULL_ON_FAILURE" was not set.');

        $bag->getInt('word');
    }

    public function testGetString()
    {
        $bag = new ParameterBag(['integer' => 123, 'bool_true' => true, 'bool_false' => false, 'string' => 'abc', 'stringable' => new class implements \Stringable {
            public function __toString(): string
            {
                return 'strval';
            }
        }]);

        $this->assertSame('123', $bag->getString('integer'), '->getString() gets a value of parameter as string');
        $this->assertSame('abc', $bag->getString('string'), '->getString() gets a value of parameter as string');
        $this->assertSame('', $bag->getString('unknown'), '->getString() returns zero if a parameter is not defined');
        $this->assertSame('foo', $bag->getString('unknown', 'foo'), '->getString() returns the default if a parameter is not defined');
        $this->assertSame('1', $bag->getString('bool_true'), '->getString() returns "1" if a parameter is true');
        $this->assertSame('', $bag->getString('bool_false', 'foo'), '->getString() returns an empty empty string if a parameter is false');
        $this->assertSame('strval', $bag->getString('stringable'), '->getString() gets a value of a stringable paramater as string');
    }

    public function testGetStringExceptionWithArray()
    {
        $bag = new ParameterBag(['key' => ['abc']]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Parameter value "key" cannot be converted to "string".');

        $bag->getString('key');
    }

    public function testGetStringExceptionWithObject()
    {
        $bag = new ParameterBag(['object' => $this]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Parameter value "object" cannot be converted to "string".');

        $bag->getString('object');
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

        $this->assertEmpty($bag->filter('nokey'), '->filter() should return empty by default if no key is found');

        $this->assertEquals('0123', $bag->filter('digits', '', \FILTER_SANITIZE_NUMBER_INT), '->filter() gets a value of parameter as integer filtering out invalid characters');

        $this->assertEquals('example@example.com', $bag->filter('email', '', \FILTER_VALIDATE_EMAIL), '->filter() gets a value of parameter as email');

        $this->assertEquals('http://example.com/foo', $bag->filter('url', '', \FILTER_VALIDATE_URL, ['flags' => \FILTER_FLAG_PATH_REQUIRED]), '->filter() gets a value of parameter as URL with a path');

        // This test is repeated for code-coverage
        $this->assertEquals('http://example.com/foo', $bag->filter('url', '', \FILTER_VALIDATE_URL, \FILTER_FLAG_PATH_REQUIRED), '->filter() gets a value of parameter as URL with a path');

        $this->assertNull($bag->filter('dec', '', \FILTER_VALIDATE_INT, [
            'flags' => \FILTER_FLAG_ALLOW_HEX | \FILTER_NULL_ON_FAILURE,
            'options' => ['min_range' => 1, 'max_range' => 0xFF],
        ]), '->filter() gets a value of parameter as integer between boundaries');

        $this->assertNull($bag->filter('hex', '', \FILTER_VALIDATE_INT, [
            'flags' => \FILTER_FLAG_ALLOW_HEX | \FILTER_NULL_ON_FAILURE,
            'options' => ['min_range' => 1, 'max_range' => 0xFF],
        ]), '->filter() gets a value of parameter as integer between boundaries');

        $this->assertEquals(['bang'], $bag->filter('array', ''), '->filter() gets a value of parameter as an array');
    }

    public function testFilterCallback()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A Closure must be passed to "Symfony\Component\HttpFoundation\ParameterBag::filter()" when FILTER_CALLBACK is used, "string" given.');

        $bag = new ParameterBag(['foo' => 'bar']);
        $bag->filter('foo', null, \FILTER_CALLBACK, ['options' => 'strtoupper']);
    }

    public function testFilterClosure()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $result = $bag->filter('foo', null, \FILTER_CALLBACK, ['options' => strtoupper(...)]);

        $this->assertSame('BAR', $result);
    }

    public function testGetIterator()
    {
        $parameters = ['foo' => 'bar', 'hello' => 'world'];
        $bag = new ParameterBag($parameters);

        $i = 0;
        foreach ($bag as $key => $val) {
            ++$i;
            $this->assertEquals($parameters[$key], $val);
        }

        $this->assertEquals(\count($parameters), $i);
    }

    public function testCount()
    {
        $parameters = ['foo' => 'bar', 'hello' => 'world'];
        $bag = new ParameterBag($parameters);

        $this->assertCount(\count($parameters), $bag);
    }

    public function testGetBoolean()
    {
        $parameters = ['string_true' => 'true', 'string_false' => 'false', 'string' => 'abc'];
        $bag = new ParameterBag($parameters);

        $this->assertTrue($bag->getBoolean('string_true'), '->getBoolean() gets the string true as boolean true');
        $this->assertFalse($bag->getBoolean('string_false'), '->getBoolean() gets the string false as boolean false');
        $this->assertFalse($bag->getBoolean('unknown'), '->getBoolean() returns false if a parameter is not defined');
        $this->assertTrue($bag->getBoolean('unknown', true), '->getBoolean() returns default if a parameter is not defined');
    }

    public function testGetBooleanExceptionWithInvalid()
    {
        $bag = new ParameterBag(['invalid' => 'foo']);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Parameter value "invalid" is invalid and flag "FILTER_NULL_ON_FAILURE" was not set.');

        $bag->getBoolean('invalid');
    }

    public function testGetEnum()
    {
        $bag = new ParameterBag(['valid-value' => 1]);

        $this->assertSame(FooEnum::Bar, $bag->getEnum('valid-value', FooEnum::class));

        $this->assertNull($bag->getEnum('invalid-key', FooEnum::class));
        $this->assertSame(FooEnum::Bar, $bag->getEnum('invalid-key', FooEnum::class, FooEnum::Bar));
    }

    public function testGetEnumThrowsExceptionWithNotBackingValue()
    {
        $bag = new ParameterBag(['invalid-value' => 2]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Parameter "invalid-value" cannot be converted to enum: 2 is not a valid backing value for enum Symfony\Component\HttpFoundation\Tests\Fixtures\FooEnum.');

        $this->assertNull($bag->getEnum('invalid-value', FooEnum::class));
    }

    public function testGetEnumThrowsExceptionWithInvalidValueType()
    {
        $bag = new ParameterBag(['invalid-value' => ['foo']]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Parameter "invalid-value" cannot be converted to enum: Symfony\Component\HttpFoundation\Tests\Fixtures\FooEnum::from(): Argument #1 ($value) must be of type int, array given.');

        $this->assertNull($bag->getEnum('invalid-value', FooEnum::class));
    }
}
