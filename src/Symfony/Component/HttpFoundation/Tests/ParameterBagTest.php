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
use Symfony\Component\HttpFoundation\ParameterBag;

class ParameterBagTest extends TestCase
{
    public function testConstructor()
    {
        $this->testAll();
    }

    public function testAll()
    {
        $bag = new ParameterBag(array('foo' => 'bar'));
        $this->assertEquals(array('foo' => 'bar'), $bag->all(), '->all() gets all the input');
    }

    public function testKeys()
    {
        $bag = new ParameterBag(array('foo' => 'bar'));
        $this->assertEquals(array('foo'), $bag->keys());
    }

    public function testAdd()
    {
        $bag = new ParameterBag(array('foo' => 'bar'));
        $bag->add(array('bar' => 'bas'));
        $this->assertEquals(array('foo' => 'bar', 'bar' => 'bas'), $bag->all());
    }

    public function testRemove()
    {
        $bag = new ParameterBag(array('foo' => 'bar'));
        $bag->add(array('bar' => 'bas'));
        $this->assertEquals(array('foo' => 'bar', 'bar' => 'bas'), $bag->all());
        $bag->remove('bar');
        $this->assertEquals(array('foo' => 'bar'), $bag->all());
    }

    public function testReplace()
    {
        $bag = new ParameterBag(array('foo' => 'bar'));

        $bag->replace(array('FOO' => 'BAR'));
        $this->assertEquals(array('FOO' => 'BAR'), $bag->all(), '->replace() replaces the input with the argument');
        $this->assertFalse($bag->has('foo'), '->replace() overrides previously set the input');
    }

    public function testGet()
    {
        $bag = new ParameterBag(array('foo' => 'bar', 'null' => null));

        $this->assertEquals('bar', $bag->get('foo'), '->get() gets the value of a parameter');
        $this->assertEquals('default', $bag->get('unknown', 'default'), '->get() returns second argument as default if a parameter is not defined');
        $this->assertNull($bag->get('null', 'default'), '->get() returns null if null is set');
    }

    public function testGetDoesNotUseDeepByDefault()
    {
        $bag = new ParameterBag(array('foo' => array('bar' => 'moo')));

        $this->assertNull($bag->get('foo[bar]'));
    }

    public function testSet()
    {
        $bag = new ParameterBag(array());

        $bag->set('foo', 'bar');
        $this->assertEquals('bar', $bag->get('foo'), '->set() sets the value of parameter');

        $bag->set('foo', 'baz');
        $this->assertEquals('baz', $bag->get('foo'), '->set() overrides previously set parameter');
    }

    public function testHas()
    {
        $bag = new ParameterBag(array('foo' => 'bar'));

        $this->assertTrue($bag->has('foo'), '->has() returns true if a parameter is defined');
        $this->assertFalse($bag->has('unknown'), '->has() return false if a parameter is not defined');
    }

    public function testGetAlpha()
    {
        $bag = new ParameterBag(array('word' => 'foo_BAR_012'));

        $this->assertEquals('fooBAR', $bag->getAlpha('word'), '->getAlpha() gets only alphabetic characters');
        $this->assertEquals('', $bag->getAlpha('unknown'), '->getAlpha() returns empty string if a parameter is not defined');
    }

    public function testGetAlnum()
    {
        $bag = new ParameterBag(array('word' => 'foo_BAR_012'));

        $this->assertEquals('fooBAR012', $bag->getAlnum('word'), '->getAlnum() gets only alphanumeric characters');
        $this->assertEquals('', $bag->getAlnum('unknown'), '->getAlnum() returns empty string if a parameter is not defined');
    }

    public function testGetDigits()
    {
        $bag = new ParameterBag(array('word' => 'foo_BAR_012'));

        $this->assertEquals('012', $bag->getDigits('word'), '->getDigits() gets only digits as string');
        $this->assertEquals('', $bag->getDigits('unknown'), '->getDigits() returns empty string if a parameter is not defined');
    }

    public function testGetInt()
    {
        $bag = new ParameterBag(array('digits' => '0123'));

        $this->assertEquals(123, $bag->getInt('digits'), '->getInt() gets a value of parameter as integer');
        $this->assertEquals(0, $bag->getInt('unknown'), '->getInt() returns zero if a parameter is not defined');
    }

    public function testGetDate()
    {
        $isoDate = '2016-07-05T15:30:00UTC';
        $bag = new ParameterBag(array(
            'd1' => '2016-01-01',
            'iso' => $isoDate,
        ));

        $date = \DateTime::createFromFormat('Y-m-d', '2016-01-01');
        $diff = $date->diff($bag->getDate('d1', 'Y-m-d'));

        $this->assertEquals(0, $diff->days, '->getDate() returns a date via the format specified');
        $this->assertNull($bag->getDate('d1', 'd/m/Y'), '->getDate() returns null if the format is not valid');
        $this->assertNull($bag->getDate('d2', 'd/m/Y'), '->getDate() returns null if the parameter is not found');
        $this->assertEquals($date->format('Ymd'), $bag->getDate('d1', 'Y-m-d', new \DateTime('2016-12-01'))->format('Ymd'), '->getDate() parses the value if the key is present');

        $date = $bag->getDate('iso', \DateTime::ISO8601);
        $this->assertEquals(new \DateTime($isoDate), $date);
        $this->assertEquals('UTC', $date->getTimezone()->getName());

        $this->assertEquals($date, $bag->getDate('nokey', \DateTime::ISO8601, $isoDate));
        $this->assertInstanceOf(\DateTimeInterface::class, $bag->getDate('nokey', \DateTime::ISO8601, $date));
        $this->assertNull($bag->getDate('nokey', 'd/m/Y', $isoDate), '->getDate() returns null when the default value is not in the specified format');

        $tz = $bag->getDate('d1', 'Y-m-d', null, new \DateTimeZone('Europe/Tirane'))->getTimezone()->getName();
        $this->assertEquals('Europe/Tirane', $tz, '->getDate() accepts a DateTimeZone object which specifies the preferred timezone');
    }

    public function testFilter()
    {
        $bag = new ParameterBag(array(
            'digits' => '0123ab',
            'email' => 'example@example.com',
            'url' => 'http://example.com/foo',
            'dec' => '256',
            'hex' => '0x100',
            'array' => array('bang'),
        ));

        $this->assertEmpty($bag->filter('nokey'), '->filter() should return empty by default if no key is found');

        $this->assertEquals('0123', $bag->filter('digits', '', FILTER_SANITIZE_NUMBER_INT), '->filter() gets a value of parameter as integer filtering out invalid characters');

        $this->assertEquals('example@example.com', $bag->filter('email', '', FILTER_VALIDATE_EMAIL), '->filter() gets a value of parameter as email');

        $this->assertEquals('http://example.com/foo', $bag->filter('url', '', FILTER_VALIDATE_URL, array('flags' => FILTER_FLAG_PATH_REQUIRED)), '->filter() gets a value of parameter as URL with a path');

        // This test is repeated for code-coverage
        $this->assertEquals('http://example.com/foo', $bag->filter('url', '', FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED), '->filter() gets a value of parameter as URL with a path');

        $this->assertFalse($bag->filter('dec', '', FILTER_VALIDATE_INT, array(
            'flags' => FILTER_FLAG_ALLOW_HEX,
            'options' => array('min_range' => 1, 'max_range' => 0xff),
        )), '->filter() gets a value of parameter as integer between boundaries');

        $this->assertFalse($bag->filter('hex', '', FILTER_VALIDATE_INT, array(
            'flags' => FILTER_FLAG_ALLOW_HEX,
            'options' => array('min_range' => 1, 'max_range' => 0xff),
        )), '->filter() gets a value of parameter as integer between boundaries');

        $this->assertEquals(array('bang'), $bag->filter('array', ''), '->filter() gets a value of parameter as an array');
    }

    public function testGetIterator()
    {
        $parameters = array('foo' => 'bar', 'hello' => 'world');
        $bag = new ParameterBag($parameters);

        $i = 0;
        foreach ($bag as $key => $val) {
            ++$i;
            $this->assertEquals($parameters[$key], $val);
        }

        $this->assertEquals(count($parameters), $i);
    }

    public function testCount()
    {
        $parameters = array('foo' => 'bar', 'hello' => 'world');
        $bag = new ParameterBag($parameters);

        $this->assertEquals(count($parameters), count($bag));
    }

    public function testGetBoolean()
    {
        $parameters = array('string_true' => 'true', 'string_false' => 'false');
        $bag = new ParameterBag($parameters);

        $this->assertTrue($bag->getBoolean('string_true'), '->getBoolean() gets the string true as boolean true');
        $this->assertFalse($bag->getBoolean('string_false'), '->getBoolean() gets the string false as boolean false');
        $this->assertFalse($bag->getBoolean('unknown'), '->getBoolean() returns false if a parameter is not defined');
    }
}
