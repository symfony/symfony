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
use Symfony\Component\HttpFoundation\InputBag;

class InputBagTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testGet()
    {
        $bag = new InputBag(['foo' => 'bar', 'null' => null]);

        $this->assertEquals('bar', $bag->get('foo'), '->get() gets the value of a parameter');
        $this->assertEquals('default', $bag->get('unknown', 'default'), '->get() returns second argument as default if a parameter is not defined');
        $this->assertNull($bag->get('null', 'default'), '->get() returns null if null is set');
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

    /**
     * @group legacy
     */
    public function testSetWithNonStringishOrArrayIsDeprecated()
    {
        $bag = new InputBag();
        $this->expectDeprecation('Since symfony/http-foundation 5.1: Passing "Symfony\Component\HttpFoundation\InputBag" as a 2nd Argument to "Symfony\Component\HttpFoundation\InputBag::set()" is deprecated, pass a string, array, or null instead.');
        $bag->set('foo', new InputBag());
    }

    /**
     * @group legacy
     */
    public function testGettingANonStringValueIsDeprecated()
    {
        $bag = new InputBag(['foo' => ['a', 'b']]);
        $this->expectDeprecation('Since symfony/http-foundation 5.1: Retrieving a non-string value from "Symfony\Component\HttpFoundation\InputBag::get()" is deprecated, and will throw a "Symfony\Component\HttpFoundation\Exception\BadRequestException" exception in Symfony 6.0, use "Symfony\Component\HttpFoundation\InputBag::all($key)" instead.');
        $bag->get('foo');
    }

    /**
     * @group legacy
     */
    public function testGetWithNonStringDefaultValueIsDeprecated()
    {
        $bag = new InputBag(['foo' => 'bar']);
        $this->expectDeprecation('Since symfony/http-foundation 5.1: Passing a non-string value as 2nd argument to "Symfony\Component\HttpFoundation\InputBag::get()" is deprecated, pass a string or null instead.');
        $bag->get('foo', ['a', 'b']);
    }

    /**
     * @group legacy
     */
    public function testFilterArrayWithoutArrayFlagIsDeprecated()
    {
        $bag = new InputBag(['foo' => ['bar', 'baz']]);
        $this->expectDeprecation('Since symfony/http-foundation 5.1: Filtering an array value with "Symfony\Component\HttpFoundation\InputBag::filter()" without passing the FILTER_REQUIRE_ARRAY or FILTER_FORCE_ARRAY flag is deprecated');
        $bag->filter('foo', \FILTER_VALIDATE_INT);
    }
}
