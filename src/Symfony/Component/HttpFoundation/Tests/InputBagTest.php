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
use Symfony\Component\HttpFoundation\InputBag;

class InputBagTest extends TestCase
{
    public function testGet()
    {
        $bag = new InputBag(['foo' => 'bar', 'null' => null, 'int' => 1, 'float' => 1.0, 'bool' => false, 'stringable' => new class() implements \Stringable {
            public function __toString(): string
            {
                return 'strval';
            }
        }]);

        $this->assertSame('bar', $bag->get('foo'), '->get() gets the value of a string parameter');
        $this->assertSame('default', $bag->get('unknown', 'default'), '->get() returns second argument as default if a parameter is not defined');
        $this->assertNull($bag->get('null', 'default'), '->get() returns null if null is set');
        $this->assertSame(1, $bag->get('int'), '->get() gets the value of an int parameter');
        $this->assertSame(1.0, $bag->get('float'), '->get() gets the value of a float parameter');
        $this->assertSame('strval', $bag->get('stringable'), '->get() gets the string value of a \Stringable parameter');
        $this->assertFalse($bag->get('bool'), '->get() gets the value of a bool parameter');
    }

    public function testGetDoesNotUseDeepByDefault()
    {
        $bag = new InputBag(['foo' => ['bar' => 'moo']]);

        $this->assertNull($bag->get('foo[bar]'));
    }

    public function testFilterArray()
    {
        $bag = new InputBag([
            'foo' => ['12', '8'],
        ]);

        $result = $bag->filter('foo', null, \FILTER_VALIDATE_INT, \FILTER_FORCE_ARRAY);
        $this->assertSame([12, 8], $result);
    }

    public function testFilterCallback()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A Closure must be passed to "Symfony\Component\HttpFoundation\InputBag::filter()" when FILTER_CALLBACK is used, "string" given.');

        $bag = new InputBag(['foo' => 'bar']);
        $bag->filter('foo', null, \FILTER_CALLBACK, ['options' => 'strtoupper']);
    }

    public function testFilterClosure()
    {
        $bag = new InputBag(['foo' => 'bar']);
        $result = $bag->filter('foo', null, \FILTER_CALLBACK, ['options' => function ($value) {
            return strtoupper($value);
        }]);

        $this->assertSame('BAR', $result);
    }

    public function testSetWithNonScalarOrArray()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected a scalar, or an array as a 2nd argument to "Symfony\Component\HttpFoundation\InputBag::set()", "Symfony\Component\HttpFoundation\InputBag" given.');

        $bag = new InputBag();
        $bag->set('foo', new InputBag());
    }

    public function testGettingANonStringValue()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Input value "foo" contains a non-scalar value.');

        $bag = new InputBag(['foo' => ['a', 'b']]);
        $bag->get('foo');
    }

    public function testGetWithNonStringDefaultValue()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected a scalar value as a 2nd argument to "Symfony\Component\HttpFoundation\InputBag::get()", "array" given.');

        $bag = new InputBag(['foo' => 'bar']);
        $bag->get('foo', ['a', 'b']);
    }

    public function testFilterArrayWithoutArrayFlag()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Input value "foo" contains an array, but "FILTER_REQUIRE_ARRAY" or "FILTER_FORCE_ARRAY" flags were not set.');

        $bag = new InputBag(['foo' => ['bar', 'baz']]);
        $bag->filter('foo', \FILTER_VALIDATE_INT);
    }
}
