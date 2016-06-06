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
use Symfony\Component\Yaml\Yaml;

class InlineTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getTestsForParse
     */
    public function testParse($yaml, $value)
    {
        $this->assertSame($value, Inline::parse($yaml), sprintf('::parse() converts an inline YAML to a PHP structure (%s)', $yaml));
    }

    /**
     * @dataProvider getTestsForParseWithMapObjects
     */
    public function testParseWithMapObjects($yaml, $value)
    {
        $actual = Inline::parse($yaml, Yaml::PARSE_OBJECT_FOR_MAP);

        $this->assertSame(serialize($value), serialize($actual));
    }

    /**
     * @group legacy
     * @dataProvider getTestsForParseWithMapObjects
     */
    public function testParseWithMapObjectsPassingTrue($yaml, $value)
    {
        $actual = Inline::parse($yaml, false, false, true);

        $this->assertSame(serialize($value), serialize($actual));
    }

    /**
     * @dataProvider getTestsForDump
     */
    public function testDump($yaml, $value)
    {
        $this->assertEquals($yaml, Inline::dump($value), sprintf('::dump() converts a PHP structure to an inline YAML (%s)', $yaml));

        $this->assertSame($value, Inline::parse(Inline::dump($value)), 'check consistency');
    }

    public function testDumpNumericValueWithLocale()
    {
        $locale = setlocale(LC_NUMERIC, 0);
        if (false === $locale) {
            $this->markTestSkipped('Your platform does not support locales.');
        }

        try {
            $requiredLocales = array('fr_FR.UTF-8', 'fr_FR.UTF8', 'fr_FR.utf-8', 'fr_FR.utf8', 'French_France.1252');
            if (false === setlocale(LC_NUMERIC, $requiredLocales)) {
                $this->markTestSkipped('Could not set any of required locales: '.implode(', ', $requiredLocales));
            }

            $this->assertEquals('1.2', Inline::dump(1.2));
            $this->assertContains('fr', strtolower(setlocale(LC_NUMERIC, 0)));
        } finally {
            setlocale(LC_NUMERIC, $locale);
        }
    }

    public function testHashStringsResemblingExponentialNumericsShouldNotBeChangedToINF()
    {
        $value = '686e444';

        $this->assertSame($value, Inline::parse(Inline::dump($value)));
    }

    /**
     * @expectedException        \Symfony\Component\Yaml\Exception\ParseException
     * @expectedExceptionMessage Found unknown escape character "\V".
     */
    public function testParseScalarWithNonEscapedBlackslashShouldThrowException()
    {
        Inline::parse('"Foo\Var"');
    }

    /**
     * @expectedException \Symfony\Component\Yaml\Exception\ParseException
     */
    public function testParseScalarWithNonEscapedBlackslashAtTheEndShouldThrowException()
    {
        Inline::parse('"Foo\\"');
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

    /**
     * @dataProvider getDataForParseReferences
     */
    public function testParseReferences($yaml, $expected)
    {
        $this->assertSame($expected, Inline::parse($yaml, 0, array('var' => 'var-value')));
    }

    /**
     * @group legacy
     * @dataProvider getDataForParseReferences
     */
    public function testParseReferencesAsFifthArgument($yaml, $expected)
    {
        $this->assertSame($expected, Inline::parse($yaml, false, false, false, array('var' => 'var-value')));
    }

    public function getDataForParseReferences()
    {
        return array(
            'scalar' => array('*var', 'var-value'),
            'list' => array('[ *var ]', array('var-value')),
            'list-in-list' => array('[[ *var ]]', array(array('var-value'))),
            'map-in-list' => array('[ { key: *var } ]', array(array('key' => 'var-value'))),
            'embedded-mapping-in-list' => array('[ key: *var ]', array(array('key' => 'var-value'))),
            'map' => array('{ key: *var }', array('key' => 'var-value')),
            'list-in-map' => array('{ key: [*var] }', array('key' => array('var-value'))),
            'map-in-map' => array('{ foo: { bar: *var } }', array('foo' => array('bar' => 'var-value'))),
        );
    }

    public function testParseMapReferenceInSequence()
    {
        $foo = array(
            'a' => 'Steve',
            'b' => 'Clark',
            'c' => 'Brian',
        );
        $this->assertSame(array($foo), Inline::parse('[*foo]', 0, array('foo' => $foo)));
    }

    /**
     * @group legacy
     */
    public function testParseMapReferenceInSequenceAsFifthArgument()
    {
        $foo = array(
            'a' => 'Steve',
            'b' => 'Clark',
            'c' => 'Brian',
        );
        $this->assertSame(array($foo), Inline::parse('[*foo]', false, false, false, array('foo' => $foo)));
    }

    /**
     * @expectedException \Symfony\Component\Yaml\Exception\ParseException
     * @expectedExceptionMessage A reference must contain at least one character.
     */
    public function testParseUnquotedAsterisk()
    {
        Inline::parse('{ foo: * }');
    }

    /**
     * @expectedException \Symfony\Component\Yaml\Exception\ParseException
     * @expectedExceptionMessage A reference must contain at least one character.
     */
    public function testParseUnquotedAsteriskFollowedByAComment()
    {
        Inline::parse('{ foo: * #foo }');
    }

    /**
     * @dataProvider getReservedIndicators
     * @expectedException Symfony\Component\Yaml\Exception\ParseException
     * @expectedExceptionMessage cannot start a plain scalar; you need to quote the scalar.
     */
    public function testParseUnquotedScalarStartingWithReservedIndicator($indicator)
    {
        Inline::parse(sprintf('{ foo: %sfoo }', $indicator));
    }

    public function getReservedIndicators()
    {
        return array(array('@'), array('`'));
    }

    /**
     * @dataProvider getScalarIndicators
     * @expectedException Symfony\Component\Yaml\Exception\ParseException
     * @expectedExceptionMessage cannot start a plain scalar; you need to quote the scalar.
     */
    public function testParseUnquotedScalarStartingWithScalarIndicator($indicator)
    {
        Inline::parse(sprintf('{ foo: %sfoo }', $indicator));
    }

    public function getScalarIndicators()
    {
        return array(array('|'), array('>'));
    }

    /**
     * @group legacy
     * throws \Symfony\Component\Yaml\Exception\ParseException in 4.0
     */
    public function testParseUnquotedScalarStartingWithPercentCharacter()
    {
        $deprecations = array();
        set_error_handler(function ($type, $msg) use (&$deprecations) {
            if (E_USER_DEPRECATED !== $type) {
                restore_error_handler();

                return call_user_func_array('PHPUnit_Util_ErrorHandler::handleError', func_get_args());
            }

            $deprecations[] = $msg;
        });

        Inline::parse('{ foo: %foo }');

        restore_error_handler();

        $this->assertCount(1, $deprecations);
        $this->assertContains('Not quoting a scalar starting with the "%" indicator character is deprecated since Symfony 3.1 and will throw a ParseException in 4.0.', $deprecations[0]);
    }

    /**
     * @dataProvider getDataForIsHash
     */
    public function testIsHash($array, $expected)
    {
        $this->assertSame($expected, Inline::isHash($array));
    }

    public function getDataForIsHash()
    {
        return array(
            array(array(), false),
            array(array(1, 2, 3), false),
            array(array(2 => 1, 1 => 2, 0 => 3), true),
            array(array('foo' => 1, 'bar' => 2), true),
        );
    }

    public function getTestsForParse()
    {
        return array(
            array('', ''),
            array('null', null),
            array('false', false),
            array('true', true),
            array('12', 12),
            array('-12', -12),
            array('"quoted string"', 'quoted string'),
            array("'quoted string'", 'quoted string'),
            array('12.30e+02', 12.30e+02),
            array('0x4D2', 0x4D2),
            array('02333', 02333),
            array('.Inf', -log(0)),
            array('-.Inf', log(0)),
            array("'686e444'", '686e444'),
            array('686e444', 646e444),
            array('123456789123456789123456789123456789', '123456789123456789123456789123456789'),
            array('"foo\r\nbar"', "foo\r\nbar"),
            array("'foo#bar'", 'foo#bar'),
            array("'foo # bar'", 'foo # bar'),
            array("'#cfcfcf'", '#cfcfcf'),
            array('::form_base.html.twig', '::form_base.html.twig'),

            // Pre-YAML-1.2 booleans
            array("'y'", 'y'),
            array("'n'", 'n'),
            array("'yes'", 'yes'),
            array("'no'", 'no'),
            array("'on'", 'on'),
            array("'off'", 'off'),

            array('2007-10-30', gmmktime(0, 0, 0, 10, 30, 2007)),
            array('2007-10-30T02:59:43Z', gmmktime(2, 59, 43, 10, 30, 2007)),
            array('2007-10-30 02:59:43 Z', gmmktime(2, 59, 43, 10, 30, 2007)),
            array('1960-10-30 02:59:43 Z', gmmktime(2, 59, 43, 10, 30, 1960)),
            array('1730-10-30T02:59:43Z', gmmktime(2, 59, 43, 10, 30, 1730)),

            array('"a \\"string\\" with \'quoted strings inside\'"', 'a "string" with \'quoted strings inside\''),
            array("'a \"string\" with ''quoted strings inside'''", 'a "string" with \'quoted strings inside\''),

            // sequences
            // urls are no key value mapping. see #3609. Valid yaml "key: value" mappings require a space after the colon
            array('[foo, http://urls.are/no/mappings, false, null, 12]', array('foo', 'http://urls.are/no/mappings', false, null, 12)),
            array('[  foo  ,   bar , false  ,  null     ,  12  ]', array('foo', 'bar', false, null, 12)),
            array('[\'foo,bar\', \'foo bar\']', array('foo,bar', 'foo bar')),

            // mappings
            array('{foo:bar,bar:foo,false:false,null:null,integer:12}', array('foo' => 'bar', 'bar' => 'foo', 'false' => false, 'null' => null, 'integer' => 12)),
            array('{ foo  : bar, bar : foo,  false  :   false,  null  :   null,  integer :  12  }', array('foo' => 'bar', 'bar' => 'foo', 'false' => false, 'null' => null, 'integer' => 12)),
            array('{foo: \'bar\', bar: \'foo: bar\'}', array('foo' => 'bar', 'bar' => 'foo: bar')),
            array('{\'foo\': \'bar\', "bar": \'foo: bar\'}', array('foo' => 'bar', 'bar' => 'foo: bar')),
            array('{\'foo\'\'\': \'bar\', "bar\"": \'foo: bar\'}', array('foo\'' => 'bar', 'bar"' => 'foo: bar')),
            array('{\'foo: \': \'bar\', "bar: ": \'foo: bar\'}', array('foo: ' => 'bar', 'bar: ' => 'foo: bar')),

            // nested sequences and mappings
            array('[foo, [bar, foo]]', array('foo', array('bar', 'foo'))),
            array('[foo, {bar: foo}]', array('foo', array('bar' => 'foo'))),
            array('{ foo: {bar: foo} }', array('foo' => array('bar' => 'foo'))),
            array('{ foo: [bar, foo] }', array('foo' => array('bar', 'foo'))),

            array('[  foo, [  bar, foo  ]  ]', array('foo', array('bar', 'foo'))),

            array('[{ foo: {bar: foo} }]', array(array('foo' => array('bar' => 'foo')))),

            array('[foo, [bar, [foo, [bar, foo]], foo]]', array('foo', array('bar', array('foo', array('bar', 'foo')), 'foo'))),

            array('[foo, {bar: foo, foo: [foo, {bar: foo}]}, [foo, {bar: foo}]]', array('foo', array('bar' => 'foo', 'foo' => array('foo', array('bar' => 'foo'))), array('foo', array('bar' => 'foo')))),

            array('[foo, bar: { foo: bar }]', array('foo', '1' => array('bar' => array('foo' => 'bar')))),
            array('[foo, \'@foo.baz\', { \'%foo%\': \'foo is %foo%\', bar: \'%foo%\' }, true, \'@service_container\']', array('foo', '@foo.baz', array('%foo%' => 'foo is %foo%', 'bar' => '%foo%'), true, '@service_container')),
        );
    }

    public function getTestsForParseWithMapObjects()
    {
        return array(
            array('', ''),
            array('null', null),
            array('false', false),
            array('true', true),
            array('12', 12),
            array('-12', -12),
            array('"quoted string"', 'quoted string'),
            array("'quoted string'", 'quoted string'),
            array('12.30e+02', 12.30e+02),
            array('0x4D2', 0x4D2),
            array('02333', 02333),
            array('.Inf', -log(0)),
            array('-.Inf', log(0)),
            array("'686e444'", '686e444'),
            array('686e444', 646e444),
            array('123456789123456789123456789123456789', '123456789123456789123456789123456789'),
            array('"foo\r\nbar"', "foo\r\nbar"),
            array("'foo#bar'", 'foo#bar'),
            array("'foo # bar'", 'foo # bar'),
            array("'#cfcfcf'", '#cfcfcf'),
            array('::form_base.html.twig', '::form_base.html.twig'),

            array('2007-10-30', gmmktime(0, 0, 0, 10, 30, 2007)),
            array('2007-10-30T02:59:43Z', gmmktime(2, 59, 43, 10, 30, 2007)),
            array('2007-10-30 02:59:43 Z', gmmktime(2, 59, 43, 10, 30, 2007)),
            array('1960-10-30 02:59:43 Z', gmmktime(2, 59, 43, 10, 30, 1960)),
            array('1730-10-30T02:59:43Z', gmmktime(2, 59, 43, 10, 30, 1730)),

            array('"a \\"string\\" with \'quoted strings inside\'"', 'a "string" with \'quoted strings inside\''),
            array("'a \"string\" with ''quoted strings inside'''", 'a "string" with \'quoted strings inside\''),

            // sequences
            // urls are no key value mapping. see #3609. Valid yaml "key: value" mappings require a space after the colon
            array('[foo, http://urls.are/no/mappings, false, null, 12]', array('foo', 'http://urls.are/no/mappings', false, null, 12)),
            array('[  foo  ,   bar , false  ,  null     ,  12  ]', array('foo', 'bar', false, null, 12)),
            array('[\'foo,bar\', \'foo bar\']', array('foo,bar', 'foo bar')),

            // mappings
            array('{foo:bar,bar:foo,false:false,null:null,integer:12}', (object) array('foo' => 'bar', 'bar' => 'foo', 'false' => false, 'null' => null, 'integer' => 12)),
            array('{ foo  : bar, bar : foo,  false  :   false,  null  :   null,  integer :  12  }', (object) array('foo' => 'bar', 'bar' => 'foo', 'false' => false, 'null' => null, 'integer' => 12)),
            array('{foo: \'bar\', bar: \'foo: bar\'}', (object) array('foo' => 'bar', 'bar' => 'foo: bar')),
            array('{\'foo\': \'bar\', "bar": \'foo: bar\'}', (object) array('foo' => 'bar', 'bar' => 'foo: bar')),
            array('{\'foo\'\'\': \'bar\', "bar\"": \'foo: bar\'}', (object) array('foo\'' => 'bar', 'bar"' => 'foo: bar')),
            array('{\'foo: \': \'bar\', "bar: ": \'foo: bar\'}', (object) array('foo: ' => 'bar', 'bar: ' => 'foo: bar')),

            // nested sequences and mappings
            array('[foo, [bar, foo]]', array('foo', array('bar', 'foo'))),
            array('[foo, {bar: foo}]', array('foo', (object) array('bar' => 'foo'))),
            array('{ foo: {bar: foo} }', (object) array('foo' => (object) array('bar' => 'foo'))),
            array('{ foo: [bar, foo] }', (object) array('foo' => array('bar', 'foo'))),

            array('[  foo, [  bar, foo  ]  ]', array('foo', array('bar', 'foo'))),

            array('[{ foo: {bar: foo} }]', array((object) array('foo' => (object) array('bar' => 'foo')))),

            array('[foo, [bar, [foo, [bar, foo]], foo]]', array('foo', array('bar', array('foo', array('bar', 'foo')), 'foo'))),

            array('[foo, {bar: foo, foo: [foo, {bar: foo}]}, [foo, {bar: foo}]]', array('foo', (object) array('bar' => 'foo', 'foo' => array('foo', (object) array('bar' => 'foo'))), array('foo', (object) array('bar' => 'foo')))),

            array('[foo, bar: { foo: bar }]', array('foo', '1' => (object) array('bar' => (object) array('foo' => 'bar')))),
            array('[foo, \'@foo.baz\', { \'%foo%\': \'foo is %foo%\', bar: \'%foo%\' }, true, \'@service_container\']', array('foo', '@foo.baz', (object) array('%foo%' => 'foo is %foo%', 'bar' => '%foo%'), true, '@service_container')),

            array('{}', new \stdClass()),
            array('{ foo  : bar, bar : {}  }', (object) array('foo' => 'bar', 'bar' => new \stdClass())),
            array('{ foo  : [], bar : {}  }', (object) array('foo' => array(), 'bar' => new \stdClass())),
            array('{foo: \'bar\', bar: {} }', (object) array('foo' => 'bar', 'bar' => new \stdClass())),
            array('{\'foo\': \'bar\', "bar": {}}', (object) array('foo' => 'bar', 'bar' => new \stdClass())),
            array('{\'foo\': \'bar\', "bar": \'{}\'}', (object) array('foo' => 'bar', 'bar' => '{}')),

            array('[foo, [{}, {}]]', array('foo', array(new \stdClass(), new \stdClass()))),
            array('[foo, [[], {}]]', array('foo', array(array(), new \stdClass()))),
            array('[foo, [[{}, {}], {}]]', array('foo', array(array(new \stdClass(), new \stdClass()), new \stdClass()))),
            array('[foo, {bar: {}}]', array('foo', '1' => (object) array('bar' => new \stdClass()))),
        );
    }

    public function getTestsForDump()
    {
        return array(
            array('null', null),
            array('false', false),
            array('true', true),
            array('12', 12),
            array("'quoted string'", 'quoted string'),
            array('!!float 1230', 12.30e+02),
            array('1234', 0x4D2),
            array('1243', 02333),
            array('.Inf', -log(0)),
            array('-.Inf', log(0)),
            array("'686e444'", '686e444'),
            array('"foo\r\nbar"', "foo\r\nbar"),
            array("'foo#bar'", 'foo#bar'),
            array("'foo # bar'", 'foo # bar'),
            array("'#cfcfcf'", '#cfcfcf'),

            array("'a \"string\" with ''quoted strings inside'''", 'a "string" with \'quoted strings inside\''),

            array("'-dash'", '-dash'),
            array("'-'", '-'),

            // Pre-YAML-1.2 booleans
            array("'y'", 'y'),
            array("'n'", 'n'),
            array("'yes'", 'yes'),
            array("'no'", 'no'),
            array("'on'", 'on'),
            array("'off'", 'off'),

            // sequences
            array('[foo, bar, false, null, 12]', array('foo', 'bar', false, null, 12)),
            array('[\'foo,bar\', \'foo bar\']', array('foo,bar', 'foo bar')),

            // mappings
            array('{ foo: bar, bar: foo, \'false\': false, \'null\': null, integer: 12 }', array('foo' => 'bar', 'bar' => 'foo', 'false' => false, 'null' => null, 'integer' => 12)),
            array('{ foo: bar, bar: \'foo: bar\' }', array('foo' => 'bar', 'bar' => 'foo: bar')),

            // nested sequences and mappings
            array('[foo, [bar, foo]]', array('foo', array('bar', 'foo'))),

            array('[foo, [bar, [foo, [bar, foo]], foo]]', array('foo', array('bar', array('foo', array('bar', 'foo')), 'foo'))),

            array('{ foo: { bar: foo } }', array('foo' => array('bar' => 'foo'))),

            array('[foo, { bar: foo }]', array('foo', array('bar' => 'foo'))),

            array('[foo, { bar: foo, foo: [foo, { bar: foo }] }, [foo, { bar: foo }]]', array('foo', array('bar' => 'foo', 'foo' => array('foo', array('bar' => 'foo'))), array('foo', array('bar' => 'foo')))),

            array('[foo, \'@foo.baz\', { \'%foo%\': \'foo is %foo%\', bar: \'%foo%\' }, true, \'@service_container\']', array('foo', '@foo.baz', array('%foo%' => 'foo is %foo%', 'bar' => '%foo%'), true, '@service_container')),

            array('{ foo: { bar: { 1: 2, baz: 3 } } }', array('foo' => array('bar' => array(1 => 2, 'baz' => 3)))),
        );
    }

    /**
     * @dataProvider getTimestampTests
     */
    public function testParseTimestampAsUnixTimestampByDefault($yaml, $year, $month, $day, $hour, $minute, $second)
    {
        $this->assertSame(gmmktime($hour, $minute, $second, $month, $day, $year), Inline::parse($yaml));
    }

    /**
     * @dataProvider getTimestampTests
     */
    public function testParseTimestampAsDateTimeObject($yaml, $year, $month, $day, $hour, $minute, $second)
    {
        $expected = new \DateTime($yaml);
        $expected->setTimeZone(new \DateTimeZone('UTC'));
        $expected->setDate($year, $month, $day);
        $expected->setTime($hour, $minute, $second);

        $this->assertEquals($expected, Inline::parse($yaml, Yaml::PARSE_DATETIME));
    }

    public function getTimestampTests()
    {
        return array(
            'canonical' => array('2001-12-15T02:59:43.1Z', 2001, 12, 15, 2, 59, 43),
            'ISO-8601' => array('2001-12-15t21:59:43.10-05:00', 2001, 12, 16, 2, 59, 43),
            'spaced' => array('2001-12-15 21:59:43.10 -5', 2001, 12, 16, 2, 59, 43),
            'date' => array('2001-12-15', 2001, 12, 15, 0, 0, 0),
        );
    }

    /**
     * @dataProvider getDateTimeDumpTests
     */
    public function testDumpDateTime($dateTime, $expected)
    {
        $this->assertSame($expected, Inline::dump($dateTime));
    }

    public function getDateTimeDumpTests()
    {
        $tests = array();

        $dateTime = new \DateTime('2001-12-15 21:59:43', new \DateTimeZone('UTC'));
        $tests['date-time-utc'] = array($dateTime, '2001-12-15T21:59:43+00:00');

        $dateTime = new \DateTimeImmutable('2001-07-15 21:59:43', new \DateTimeZone('Europe/Berlin'));
        $tests['immutable-date-time-europe-berlin'] = array($dateTime, '2001-07-15T21:59:43+02:00');

        return $tests;
    }

    /**
     * @dataProvider getBinaryData
     */
    public function testParseBinaryData($data)
    {
        $this->assertSame('Hello world', Inline::parse($data));
    }

    public function getBinaryData()
    {
        return array(
            'enclosed with double quotes' => array('!!binary "SGVsbG8gd29ybGQ="'),
            'enclosed with single quotes' => array("!!binary 'SGVsbG8gd29ybGQ='"),
            'containing spaces' => array('!!binary  "SGVs bG8gd 29ybGQ="'),
        );
    }

    /**
     * @dataProvider getInvalidBinaryData
     */
    public function testParseInvalidBinaryData($data, $expectedMessage)
    {
        $this->setExpectedExceptionRegExp('\Symfony\Component\Yaml\Exception\ParseException', $expectedMessage);

        Inline::parse($data);
    }

    public function getInvalidBinaryData()
    {
        return array(
            'length not a multiple of four' => array('!!binary "SGVsbG8d29ybGQ="', '/The normalized base64 encoded data \(data without whitespace characters\) length must be a multiple of four \(\d+ bytes given\)/'),
            'invalid characters' => array('!!binary "SGVsbG8#d29ybGQ="', '/The base64 encoded data \(.*\) contains invalid characters/'),
            'too many equals characters' => array('!!binary "SGVsbG8gd29yb==="', '/The base64 encoded data \(.*\) contains invalid characters/'),
            'misplaced equals character' => array('!!binary "SGVsbG8gd29ybG=Q"', '/The base64 encoded data \(.*\) contains invalid characters/'),
        );
    }
}
