<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml\Tests;

use Symfony\Component\Yaml\Inline;

class InlineTest extends \PHPUnit_Framework_TestCase
{
    public function testParse()
    {
        foreach ($this->getTestsForParse() as $yaml => $value) {
            $this->assertSame($value, Inline::parse($yaml), sprintf('::parse() converts an inline YAML to a PHP structure (%s)', $yaml));
        }
    }

    public function testParseWithMapObjects()
    {
        foreach ($this->getTestsForMapObjectParse() as $yaml => $value) {
            $actual = Inline::parse($yaml, false, false, true);
            if (true === is_object($value)) {
                $this->assertInstanceOf(get_class($value), $actual);
                $this->assertEquals(get_object_vars($value), get_object_vars($actual));
            } elseif (true === is_array($value)) {
                $this->assertEquals($value, $actual);
                $this->assertMixedArraysSame($value, $actual);
            } else {
                $this->assertSame($value, $actual);
            }
        }

    }

    public function testDump()
    {
        $testsForDump = $this->getTestsForDump();

        foreach ($testsForDump as $yaml => $value) {
            $this->assertEquals($yaml, Inline::dump($value), sprintf('::dump() converts a PHP structure to an inline YAML (%s)', $yaml));
        }

        foreach ($this->getTestsForParse() as $value) {
            $this->assertEquals($value, Inline::parse(Inline::dump($value)), 'check consistency');
        }

        foreach ($testsForDump as $value) {
            $this->assertEquals($value, Inline::parse(Inline::dump($value)), 'check consistency');
        }
    }

    public function testDumpNumericValueWithLocale()
    {
        $locale = setlocale(LC_NUMERIC, 0);
        if (false === $locale) {
            $this->markTestSkipped('Your platform does not support locales.');
        }

        $required_locales = array('fr_FR.UTF-8', 'fr_FR.UTF8', 'fr_FR.utf-8', 'fr_FR.utf8', 'French_France.1252');
        if (false === setlocale(LC_ALL, $required_locales)) {
            $this->markTestSkipped('Could not set any of required locales: '.implode(", ", $required_locales));
        }

        $this->assertEquals('1.2', Inline::dump(1.2));
        $this->assertContains('fr', strtolower(setlocale(LC_NUMERIC, 0)));

        setlocale(LC_ALL, $locale);
    }

    public function testHashStringsResemblingExponentialNumericsShouldNotBeChangedToINF()
    {
        $value = '686e444';

        $this->assertSame($value, Inline::parse(Inline::dump($value)));
    }

    /**
     * @expectedException \Symfony\Component\Yaml\Exception\ParseException
     */
    public function testParseScalarWithIncorrectlyQuotedStringShouldThrowException()
    {
        $value = "'don't do somthin' like that'";
        Inline::parse($value);
    }

    /**
     * @expectedException \Symfony\Component\Yaml\Exception\ParseException
     */
    public function testParseScalarWithIncorrectlyDoubleQuotedStringShouldThrowException()
    {
        $value = '"don"t do somthin" like that"';
        Inline::parse($value);
    }

    /**
     * @expectedException \Symfony\Component\Yaml\Exception\ParseException
     */
    public function testParseInvalidMappingKeyShouldThrowException()
    {
        $value = '{ "foo " bar": "bar" }';
        Inline::parse($value);
    }

    /**
     * @expectedException \Symfony\Component\Yaml\Exception\ParseException
     */
    public function testParseInvalidMappingShouldThrowException()
    {
        Inline::parse('[foo] bar');
    }

    /**
     * @expectedException \Symfony\Component\Yaml\Exception\ParseException
     */
    public function testParseInvalidSequenceShouldThrowException()
    {
        Inline::parse('{ foo: bar } bar');
    }

    public function testParseScalarWithCorrectlyQuotedStringShouldReturnString()
    {
        $value = "'don''t do somthin'' like that'";
        $expect = "don't do somthin' like that";

        $this->assertSame($expect, Inline::parseScalar($value));
    }

    protected function getTestsForParse()
    {
        return array(
            '' => '',
            'null' => null,
            'false' => false,
            'true' => true,
            '12' => 12,
            '-12' => -12,
            '"quoted string"' => 'quoted string',
            "'quoted string'" => 'quoted string',
            '12.30e+02' => 12.30e+02,
            '0x4D2' => 0x4D2,
            '02333' => 02333,
            '.Inf' => -log(0),
            '-.Inf' => log(0),
            "'686e444'" => '686e444',
            '686e444' => 646e444,
            '123456789123456789123456789123456789' => '123456789123456789123456789123456789',
            '"foo\r\nbar"' => "foo\r\nbar",
            "'foo#bar'" => 'foo#bar',
            "'foo # bar'" => 'foo # bar',
            "'#cfcfcf'" => '#cfcfcf',
            '::form_base.html.twig' => '::form_base.html.twig',

            '2007-10-30' => mktime(0, 0, 0, 10, 30, 2007),
            '2007-10-30T02:59:43Z' => gmmktime(2, 59, 43, 10, 30, 2007),
            '2007-10-30 02:59:43 Z' => gmmktime(2, 59, 43, 10, 30, 2007),
            '1960-10-30 02:59:43 Z' => gmmktime(2, 59, 43, 10, 30, 1960),
            '1730-10-30T02:59:43Z' => gmmktime(2, 59, 43, 10, 30, 1730),

            '"a \\"string\\" with \'quoted strings inside\'"' => 'a "string" with \'quoted strings inside\'',
            "'a \"string\" with ''quoted strings inside'''" => 'a "string" with \'quoted strings inside\'',

            // sequences
            // urls are no key value mapping. see #3609. Valid yaml "key: value" mappings require a space after the colon
            '[foo, http://urls.are/no/mappings, false, null, 12]' => array('foo', 'http://urls.are/no/mappings', false, null, 12),
            '[  foo  ,   bar , false  ,  null     ,  12  ]' => array('foo', 'bar', false, null, 12),
            '[\'foo,bar\', \'foo bar\']' => array('foo,bar', 'foo bar'),

            // mappings
            '{foo:bar,bar:foo,false:false,null:null,integer:12}' => array('foo' => 'bar', 'bar' => 'foo', 'false' => false, 'null' => null, 'integer' => 12),
            '{ foo  : bar, bar : foo,  false  :   false,  null  :   null,  integer :  12  }' => array('foo' => 'bar', 'bar' => 'foo', 'false' => false, 'null' => null, 'integer' => 12),
            '{foo: \'bar\', bar: \'foo: bar\'}' => array('foo' => 'bar', 'bar' => 'foo: bar'),
            '{\'foo\': \'bar\', "bar": \'foo: bar\'}' => array('foo' => 'bar', 'bar' => 'foo: bar'),
            '{\'foo\'\'\': \'bar\', "bar\"": \'foo: bar\'}' => array('foo\'' => 'bar', "bar\"" => 'foo: bar'),
            '{\'foo: \': \'bar\', "bar: ": \'foo: bar\'}' => array('foo: ' => 'bar', "bar: " => 'foo: bar'),

            // nested sequences and mappings
            '[foo, [bar, foo]]' => array('foo', array('bar', 'foo')),
            '[foo, {bar: foo}]' => array('foo', array('bar' => 'foo')),
            '{ foo: {bar: foo} }' => array('foo' => array('bar' => 'foo')),
            '{ foo: [bar, foo] }' => array('foo' => array('bar', 'foo')),

            '[  foo, [  bar, foo  ]  ]' => array('foo', array('bar', 'foo')),

            '[{ foo: {bar: foo} }]' => array(array('foo' => array('bar' => 'foo'))),

            '[foo, [bar, [foo, [bar, foo]], foo]]' => array('foo', array('bar', array('foo', array('bar', 'foo')), 'foo')),

            '[foo, {bar: foo, foo: [foo, {bar: foo}]}, [foo, {bar: foo}]]' => array('foo', array('bar' => 'foo', 'foo' => array('foo', array('bar' => 'foo'))), array('foo', array('bar' => 'foo'))),

            '[foo, bar: { foo: bar }]' => array('foo', '1' => array('bar' => array('foo' => 'bar'))),
            '[foo, \'@foo.baz\', { \'%foo%\': \'foo is %foo%\', bar: \'%foo%\' }, true, \'@service_container\']' => array('foo', '@foo.baz', array('%foo%' => 'foo is %foo%', 'bar' => '%foo%',), true, '@service_container',),
        );
    }

    protected function getTestsForMapObjectParse()
    {
        return array(
            '' => '',
            'null' => null,
            'false' => false,
            'true' => true,
            '12' => 12,
            '-12' => -12,
            '"quoted string"' => 'quoted string',
            "'quoted string'" => 'quoted string',
            '12.30e+02' => 12.30e+02,
            '0x4D2' => 0x4D2,
            '02333' => 02333,
            '.Inf' => -log(0),
            '-.Inf' => log(0),
            "'686e444'" => '686e444',
            '686e444' => 646e444,
            '123456789123456789123456789123456789' => '123456789123456789123456789123456789',
            '"foo\r\nbar"' => "foo\r\nbar",
            "'foo#bar'" => 'foo#bar',
            "'foo # bar'" => 'foo # bar',
            "'#cfcfcf'" => '#cfcfcf',
            '::form_base.html.twig' => '::form_base.html.twig',

            '2007-10-30' => mktime(0, 0, 0, 10, 30, 2007),
            '2007-10-30T02:59:43Z' => gmmktime(2, 59, 43, 10, 30, 2007),
            '2007-10-30 02:59:43 Z' => gmmktime(2, 59, 43, 10, 30, 2007),
            '1960-10-30 02:59:43 Z' => gmmktime(2, 59, 43, 10, 30, 1960),
            '1730-10-30T02:59:43Z' => gmmktime(2, 59, 43, 10, 30, 1730),

            '"a \\"string\\" with \'quoted strings inside\'"' => 'a "string" with \'quoted strings inside\'',
            "'a \"string\" with ''quoted strings inside'''" => 'a "string" with \'quoted strings inside\'',

            // sequences
            // urls are no key value mapping. see #3609. Valid yaml "key: value" mappings require a space after the colon
            '[foo, http://urls.are/no/mappings, false, null, 12]' => array('foo', 'http://urls.are/no/mappings', false, null, 12),
            '[  foo  ,   bar , false  ,  null     ,  12  ]' => array('foo', 'bar', false, null, 12),
            '[\'foo,bar\', \'foo bar\']' => array('foo,bar', 'foo bar'),

            // mappings
            '{foo:bar,bar:foo,false:false,null:null,integer:12}' => (object) array('foo' => 'bar', 'bar' => 'foo', 'false' => false, 'null' => null, 'integer' => 12),
            '{ foo  : bar, bar : foo,  false  :   false,  null  :   null,  integer :  12  }' => (object) array('foo' => 'bar', 'bar' => 'foo', 'false' => false, 'null' => null, 'integer' => 12),
            '{foo: \'bar\', bar: \'foo: bar\'}' => (object) array('foo' => 'bar', 'bar' => 'foo: bar'),
            '{\'foo\': \'bar\', "bar": \'foo: bar\'}' => (object) array('foo' => 'bar', 'bar' => 'foo: bar'),
            '{\'foo\'\'\': \'bar\', "bar\"": \'foo: bar\'}' => (object) array('foo\'' => 'bar', "bar\"" => 'foo: bar'),
            '{\'foo: \': \'bar\', "bar: ": \'foo: bar\'}' => (object) array('foo: ' => 'bar', "bar: " => 'foo: bar'),

            // nested sequences and mappings
            '[foo, [bar, foo]]' => array('foo', array('bar', 'foo')),
            '[foo, {bar: foo}]' => array('foo', (object) array('bar' => 'foo')),
            '{ foo: {bar: foo} }' => (object) array('foo' => (object) array('bar' => 'foo')),
            '{ foo: [bar, foo] }' => (object) array('foo' => array('bar', 'foo')),

            '[  foo, [  bar, foo  ]  ]' => array('foo', array('bar', 'foo')),

            '[{ foo: {bar: foo} }]' => array((object) array('foo' => (object) array('bar' => 'foo'))),

            '[foo, [bar, [foo, [bar, foo]], foo]]' => array('foo', array('bar', array('foo', array('bar', 'foo')), 'foo')),

            '[foo, {bar: foo, foo: [foo, {bar: foo}]}, [foo, {bar: foo}]]' => array('foo', (object) array('bar' => 'foo', 'foo' => array('foo', (object) array('bar' => 'foo'))), array('foo', (object) array('bar' => 'foo'))),

            '[foo, bar: { foo: bar }]' => array('foo', '1' => (object) array('bar' => (object) array('foo' => 'bar'))),
            '[foo, \'@foo.baz\', { \'%foo%\': \'foo is %foo%\', bar: \'%foo%\' }, true, \'@service_container\']' => array('foo', '@foo.baz', (object) array('%foo%' => 'foo is %foo%', 'bar' => '%foo%',), true, '@service_container',),

            '{}' => new \stdClass(),
            '{ foo  : bar, bar : {}  }' => (object) array('foo' => 'bar', 'bar' => new \stdClass()),
            '{ foo  : [], bar : {}  }' => (object) array('foo' => array(), 'bar' => new \stdClass()),
            '{foo: \'bar\', bar: {} }' => (object) array('foo' => 'bar', 'bar' => new \stdClass()),
            '{\'foo\': \'bar\', "bar": {}}' => (object) array('foo' => 'bar', 'bar' => new \stdClass()),
            '{\'foo\': \'bar\', "bar": \'{}\'}' => (object) array('foo' => 'bar', 'bar' => '{}'),

            '[foo, [{}, {}]]' => array('foo', array(new \stdClass(), new \stdClass())),
            '[foo, [[], {}]]' => array('foo', array(array(), new \stdClass())),
            '[foo, [[{}, {}], {}]]' => array('foo', array(array(new \stdClass(), new \stdClass()), new \stdClass())),
            '[foo, {bar: {}}]' => array('foo', '1' => (object) array('bar' => new \stdClass())),
        );
    }

    protected function getTestsForDump()
    {
        return array(
            'null' => null,
            'false' => false,
            'true' => true,
            '12' => 12,
            "'quoted string'" => 'quoted string',
            '12.30e+02' => 12.30e+02,
            '1234' => 0x4D2,
            '1243' => 02333,
            '.Inf' => -log(0),
            '-.Inf' => log(0),
            "'686e444'" => '686e444',
            '"foo\r\nbar"' => "foo\r\nbar",
            "'foo#bar'" => 'foo#bar',
            "'foo # bar'" => 'foo # bar',
            "'#cfcfcf'" => '#cfcfcf',

            "'a \"string\" with ''quoted strings inside'''" => 'a "string" with \'quoted strings inside\'',

            "'-dash'" => '-dash',
            "'-'" => '-',

            // sequences
            '[foo, bar, false, null, 12]' => array('foo', 'bar', false, null, 12),
            '[\'foo,bar\', \'foo bar\']' => array('foo,bar', 'foo bar'),

            // mappings
            '{ foo: bar, bar: foo, \'false\': false, \'null\': null, integer: 12 }' => array('foo' => 'bar', 'bar' => 'foo', 'false' => false, 'null' => null, 'integer' => 12),
            '{ foo: bar, bar: \'foo: bar\' }' => array('foo' => 'bar', 'bar' => 'foo: bar'),

            // nested sequences and mappings
            '[foo, [bar, foo]]' => array('foo', array('bar', 'foo')),

            '[foo, [bar, [foo, [bar, foo]], foo]]' => array('foo', array('bar', array('foo', array('bar', 'foo')), 'foo')),

            '{ foo: { bar: foo } }' => array('foo' => array('bar' => 'foo')),

            '[foo, { bar: foo }]' => array('foo', array('bar' => 'foo')),

            '[foo, { bar: foo, foo: [foo, { bar: foo }] }, [foo, { bar: foo }]]' => array('foo', array('bar' => 'foo', 'foo' => array('foo', array('bar' => 'foo'))), array('foo', array('bar' => 'foo'))),

            '[foo, \'@foo.baz\', { \'%foo%\': \'foo is %foo%\', bar: \'%foo%\' }, true, \'@service_container\']' => array('foo', '@foo.baz', array('%foo%' => 'foo is %foo%', 'bar' => '%foo%',), true, '@service_container',),
        );
    }

    protected function assertMixedArraysSame($a, $b)
    {

        foreach ($a as $key => $value) {
            if (array_key_exists($key, $b)) {
                if (is_array($value)) {
                    $this->assertMixedArraysSame($value, $b[$key]);
                } else {
                    if (true === is_object($value)) {
                        $this->assertEquals($value, $b[$key]);
                        $this->assertInstanceOf(get_class($value), $b[$key]);
                        $this->assertEquals(get_object_vars($value), get_object_vars($b[$key]));
                    } else {
                        $this->assertSame($value, $b[$key]);
                    }
                }
            } else {
                $this->assertFail();
            }
        }

    }
}
