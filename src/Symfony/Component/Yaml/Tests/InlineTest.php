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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Inline;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Tests\Fixtures\FooBackedEnum;
use Symfony\Component\Yaml\Tests\Fixtures\FooUnitEnum;
use Symfony\Component\Yaml\Yaml;

class InlineTest extends TestCase
{
    protected function setUp(): void
    {
        Inline::initialize(0, 0);
    }

    /**
     * @dataProvider getTestsForParse
     */
    public function testParse($yaml, $value, $flags = 0)
    {
        $this->assertSame($value, Inline::parse($yaml, $flags), sprintf('::parse() converts an inline YAML to a PHP structure (%s)', $yaml));
    }

    /**
     * @dataProvider getTestsForParseWithMapObjects
     */
    public function testParseWithMapObjects($yaml, $value, $flags = Yaml::PARSE_OBJECT_FOR_MAP)
    {
        $actual = Inline::parse($yaml, $flags);

        $this->assertSame(serialize($value), serialize($actual));
    }

    /**
     * @dataProvider getTestsForParsePhpConstants
     */
    public function testParsePhpConstants($yaml, $value)
    {
        $actual = Inline::parse($yaml, Yaml::PARSE_CONSTANT);

        $this->assertSame($value, $actual);
    }

    public static function getTestsForParsePhpConstants()
    {
        return [
            ['!php/const Symfony\Component\Yaml\Yaml::PARSE_CONSTANT', Yaml::PARSE_CONSTANT],
            ['!php/const PHP_INT_MAX', \PHP_INT_MAX],
            ['[!php/const PHP_INT_MAX]', [\PHP_INT_MAX]],
            ['{ foo: !php/const PHP_INT_MAX }', ['foo' => \PHP_INT_MAX]],
            ['{ !php/const PHP_INT_MAX: foo }', [\PHP_INT_MAX => 'foo']],
            ['!php/const NULL', null],
        ];
    }

    public function testParsePhpConstantThrowsExceptionWhenUndefined()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('The constant "WRONG_CONSTANT" is not defined');
        Inline::parse('!php/const WRONG_CONSTANT', Yaml::PARSE_CONSTANT);
    }

    public function testParsePhpEnumThrowsExceptionWhenUndefined()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('The enum "SomeEnum::Foo" is not defined');
        Inline::parse('!php/enum SomeEnum::Foo', Yaml::PARSE_CONSTANT);
    }

    public function testParsePhpEnumThrowsExceptionWhenNotAnEnum()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('The string "PHP_INT_MAX" is not the name of a valid enum');
        Inline::parse('!php/enum PHP_INT_MAX', Yaml::PARSE_CONSTANT);
    }

    public function testParsePhpEnumThrowsExceptionWhenNotBacked()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('The enum "Symfony\Component\Yaml\Tests\Fixtures\FooUnitEnum::BAR" defines no value next to its name');
        Inline::parse('!php/enum Symfony\Component\Yaml\Tests\Fixtures\FooUnitEnum::BAR->value', Yaml::PARSE_CONSTANT);
    }

    public function testParsePhpConstantThrowsExceptionOnInvalidType()
    {
        $this->assertNull(Inline::parse('!php/const PHP_INT_MAX'));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessageMatches('#The string "!php/const PHP_INT_MAX" could not be parsed as a constant.*#');
        Inline::parse('!php/const PHP_INT_MAX', Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
    }

    public function testParsePhpEnumThrowsExceptionOnInvalidType()
    {
        $this->assertNull(Inline::parse('!php/enum SomeEnum::Foo'));

        $this->expectException(ParseException::class);
        $this->expectExceptionMessageMatches('#The string "!php/enum SomeEnum::Foo" could not be parsed as an enum.*#');
        Inline::parse('!php/enum SomeEnum::Foo', Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
    }

    /**
     * @dataProvider getTestsForDump
     */
    public function testDump($yaml, $value, $parseFlags = 0)
    {
        $this->assertEquals($yaml, Inline::dump($value), sprintf('::dump() converts a PHP structure to an inline YAML (%s)', $yaml));

        $this->assertSame($value, Inline::parse(Inline::dump($value), $parseFlags), 'check consistency');
    }

    public function testDumpNumericValueWithLocale()
    {
        $locale = setlocale(\LC_NUMERIC, 0);
        if (false === $locale) {
            $this->markTestSkipped('Your platform does not support locales.');
        }

        try {
            $requiredLocales = ['fr_FR.UTF-8', 'fr_FR.UTF8', 'fr_FR.utf-8', 'fr_FR.utf8', 'French_France.1252'];
            if (false === setlocale(\LC_NUMERIC, $requiredLocales)) {
                $this->markTestSkipped('Could not set any of required locales: '.implode(', ', $requiredLocales));
            }

            $this->assertEquals('1.2', Inline::dump(1.2));
            $this->assertStringContainsStringIgnoringCase('fr', setlocale(\LC_NUMERIC, 0));
        } finally {
            setlocale(\LC_NUMERIC, $locale);
        }
    }

    public function testHashStringsResemblingExponentialNumericsShouldNotBeChangedToINF()
    {
        $value = '686e444';

        $this->assertSame($value, Inline::parse(Inline::dump($value)));
    }

    public function testParseScalarWithNonEscapedBlackslashShouldThrowException()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Found unknown escape character "\V".');
        Inline::parse('"Foo\Var"');
    }

    public function testParseScalarWithNonEscapedBlackslashAtTheEndShouldThrowException()
    {
        $this->expectException(ParseException::class);
        Inline::parse('"Foo\\"');
    }

    public function testParseScalarWithIncorrectlyQuotedStringShouldThrowException()
    {
        $this->expectException(ParseException::class);
        $value = "'don't do somthin' like that'";
        Inline::parse($value);
    }

    public function testParseScalarWithIncorrectlyDoubleQuotedStringShouldThrowException()
    {
        $this->expectException(ParseException::class);
        $value = '"don"t do somthin" like that"';
        Inline::parse($value);
    }

    public function testParseInvalidMappingKeyShouldThrowException()
    {
        $this->expectException(ParseException::class);
        $value = '{ "foo " bar": "bar" }';
        Inline::parse($value);
    }

    public function testParseMappingKeyWithColonNotFollowedBySpace()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Colons must be followed by a space or an indication character (i.e. " ", ",", "[", "]", "{", "}")');
        Inline::parse('{foo:""}');
    }

    public function testParseInvalidMappingShouldThrowException()
    {
        $this->expectException(ParseException::class);
        Inline::parse('[foo] bar');
    }

    public function testParseInvalidSequenceShouldThrowException()
    {
        $this->expectException(ParseException::class);
        Inline::parse('{ foo: bar } bar');
    }

    public function testParseInvalidTaggedSequenceShouldThrowException()
    {
        $this->expectException(ParseException::class);
        Inline::parse('!foo { bar: baz } qux', Yaml::PARSE_CUSTOM_TAGS);
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
        $references = ['var' => 'var-value'];
        $this->assertSame($expected, Inline::parse($yaml, 0, $references));
    }

    public static function getDataForParseReferences()
    {
        return [
            'scalar' => ['*var', 'var-value'],
            'list' => ['[ *var ]', ['var-value']],
            'list-in-list' => ['[[ *var ]]', [['var-value']]],
            'map-in-list' => ['[ { key: *var } ]', [['key' => 'var-value']]],
            'embedded-mapping-in-list' => ['[ key: *var ]', [['key' => 'var-value']]],
            'map' => ['{ key: *var }', ['key' => 'var-value']],
            'list-in-map' => ['{ key: [*var] }', ['key' => ['var-value']]],
            'map-in-map' => ['{ foo: { bar: *var } }', ['foo' => ['bar' => 'var-value']]],
        ];
    }

    public function testParseMapReferenceInSequence()
    {
        $foo = [
            'a' => 'Steve',
            'b' => 'Clark',
            'c' => 'Brian',
        ];
        $references = ['foo' => $foo];
        $this->assertSame([$foo], Inline::parse('[*foo]', 0, $references));
    }

    public function testParseUnquotedAsterisk()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('A reference must contain at least one character at line 1.');
        Inline::parse('{ foo: * }');
    }

    public function testParseUnquotedAsteriskFollowedByAComment()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('A reference must contain at least one character at line 1.');
        Inline::parse('{ foo: * #foo }');
    }

    /**
     * @dataProvider getReservedIndicators
     */
    public function testParseUnquotedScalarStartingWithReservedIndicator($indicator)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage(sprintf('cannot start a plain scalar; you need to quote the scalar at line 1 (near "%sfoo").', $indicator));

        Inline::parse(sprintf('{ foo: %sfoo }', $indicator));
    }

    public static function getReservedIndicators()
    {
        return [['@'], ['`']];
    }

    /**
     * @dataProvider getScalarIndicators
     */
    public function testParseUnquotedScalarStartingWithScalarIndicator($indicator)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage(sprintf('cannot start a plain scalar; you need to quote the scalar at line 1 (near "%sfoo").', $indicator));

        Inline::parse(sprintf('{ foo: %sfoo }', $indicator));
    }

    public static function getScalarIndicators()
    {
        return [['|'], ['>'], ['%']];
    }

    /**
     * @dataProvider getDataForIsHash
     */
    public function testIsHash($array, $expected)
    {
        $this->assertSame($expected, Inline::isHash($array));
    }

    public static function getDataForIsHash()
    {
        return [
            [[], false],
            [[1, 2, 3], false],
            [[2 => 1, 1 => 2, 0 => 3], true],
            [['foo' => 1, 'bar' => 2], true],
        ];
    }

    public static function getTestsForParse()
    {
        return [
            ['', ''],
            ['null', null],
            ['false', false],
            ['true', true],
            ['12', 12],
            ['-12', -12],
            ['1_2', 12],
            ['_12', '_12'],
            ['12_', 12],
            ['"quoted string"', 'quoted string'],
            ["'quoted string'", 'quoted string'],
            ['1234.0', 1234.0],
            ['12.30e+02', 12.30e+02],
            ['123.45_67', 123.4567],
            ['0x4D2', 0x4D2],
            ['0x_4_D_2_', 0x4D2],
            ['0o2333', 02333],
            ['0o_2_3_3_3', 02333],
            ['.Inf', -log(0)],
            ['-.Inf', log(0)],
            ["'686e444'", '686e444'],
            ['686e444', 646e444],
            ['123456789123456789123456789123456789', '123456789123456789123456789123456789'],
            ['"foo\r\nbar"', "foo\r\nbar"],
            ["'foo#bar'", 'foo#bar'],
            ["'foo # bar'", 'foo # bar'],
            ["'#cfcfcf'", '#cfcfcf'],
            ['::form_base.html.twig', '::form_base.html.twig'],

            // Pre-YAML-1.2 booleans
            ["'y'", 'y'],
            ["'n'", 'n'],
            ["'yes'", 'yes'],
            ["'no'", 'no'],
            ["'on'", 'on'],
            ["'off'", 'off'],

            ['2007-10-30', gmmktime(0, 0, 0, 10, 30, 2007)],
            ['2007-10-30T02:59:43Z', gmmktime(2, 59, 43, 10, 30, 2007)],
            ['2007-10-30 02:59:43 Z', gmmktime(2, 59, 43, 10, 30, 2007)],
            ['1960-10-30 02:59:43 Z', gmmktime(2, 59, 43, 10, 30, 1960)],
            ['1730-10-30T02:59:43Z', \PHP_INT_SIZE === 4 ? '-7547547617' : gmmktime(2, 59, 43, 10, 30, 1730)],

            ['"a \\"string\\" with \'quoted strings inside\'"', 'a "string" with \'quoted strings inside\''],
            ["'a \"string\" with ''quoted strings inside'''", 'a "string" with \'quoted strings inside\''],

            // sequences
            // urls are no key value mapping. see #3609. Valid yaml "key: value" mappings require a space after the colon
            ['[foo, http://urls.are/no/mappings, false, null, 12]', ['foo', 'http://urls.are/no/mappings', false, null, 12]],
            ['[  foo  ,   bar , false  ,  null     ,  12  ]', ['foo', 'bar', false, null, 12]],
            ['[\'foo,bar\', \'foo bar\']', ['foo,bar', 'foo bar']],

            // mappings
            ['{foo: bar,bar: foo,"false": false, "null": null,integer: 12}', ['foo' => 'bar', 'bar' => 'foo', 'false' => false, 'null' => null, 'integer' => 12]],
            ['{ foo  : bar, bar : foo, "false"  :   false,  "null"  :   null,  integer :  12  }', ['foo' => 'bar', 'bar' => 'foo', 'false' => false, 'null' => null, 'integer' => 12]],
            ['{foo: \'bar\', bar: \'foo: bar\'}', ['foo' => 'bar', 'bar' => 'foo: bar']],
            ['{\'foo\': \'bar\', "bar": \'foo: bar\'}', ['foo' => 'bar', 'bar' => 'foo: bar']],
            ['{\'foo\'\'\': \'bar\', "bar\"": \'foo: bar\'}', ['foo\'' => 'bar', 'bar"' => 'foo: bar']],
            ['{\'foo: \': \'bar\', "bar: ": \'foo: bar\'}', ['foo: ' => 'bar', 'bar: ' => 'foo: bar']],
            ['{"foo:bar": "baz"}', ['foo:bar' => 'baz']],
            ['{"foo":"bar"}', ['foo' => 'bar']],

            // nested sequences and mappings
            ['[foo, [bar, foo]]', ['foo', ['bar', 'foo']]],
            ['[foo, {bar: foo}]', ['foo', ['bar' => 'foo']]],
            ['{ foo: {bar: foo} }', ['foo' => ['bar' => 'foo']]],
            ['{ foo: [bar, foo] }', ['foo' => ['bar', 'foo']]],
            ['{ foo:{bar: foo} }', ['foo' => ['bar' => 'foo']]],
            ['{ foo:[bar, foo] }', ['foo' => ['bar', 'foo']]],

            ['[  foo, [  bar, foo  ]  ]', ['foo', ['bar', 'foo']]],

            ['[{ foo: {bar: foo} }]', [['foo' => ['bar' => 'foo']]]],

            ['[foo, [bar, [foo, [bar, foo]], foo]]', ['foo', ['bar', ['foo', ['bar', 'foo']], 'foo']]],

            ['[foo, {bar: foo, foo: [foo, {bar: foo}]}, [foo, {bar: foo}]]', ['foo', ['bar' => 'foo', 'foo' => ['foo', ['bar' => 'foo']]], ['foo', ['bar' => 'foo']]]],

            ['[foo, bar: { foo: bar }]', ['foo', '1' => ['bar' => ['foo' => 'bar']]]],
            ['[foo, \'@foo.baz\', { \'%foo%\': \'foo is %foo%\', bar: \'%foo%\' }, true, \'@service_container\']', ['foo', '@foo.baz', ['%foo%' => 'foo is %foo%', 'bar' => '%foo%'], true, '@service_container']],
        ];
    }

    public static function getTestsForParseWithMapObjects()
    {
        return [
            ['', ''],
            ['null', null],
            ['false', false],
            ['true', true],
            ['12', 12],
            ['-12', -12],
            ['"quoted string"', 'quoted string'],
            ["'quoted string'", 'quoted string'],
            ['12.30e+02', 12.30e+02],
            ['0x4D2', 0x4D2],
            ['0o2333', 02333],
            ['.Inf', -log(0)],
            ['-.Inf', log(0)],
            ["'686e444'", '686e444'],
            ['686e444', 646e444],
            ['123456789123456789123456789123456789', '123456789123456789123456789123456789'],
            ['"foo\r\nbar"', "foo\r\nbar"],
            ["'foo#bar'", 'foo#bar'],
            ["'foo # bar'", 'foo # bar'],
            ["'#cfcfcf'", '#cfcfcf'],
            ['::form_base.html.twig', '::form_base.html.twig'],

            ['2007-10-30', gmmktime(0, 0, 0, 10, 30, 2007)],
            ['2007-10-30T02:59:43Z', gmmktime(2, 59, 43, 10, 30, 2007)],
            ['2007-10-30 02:59:43 Z', gmmktime(2, 59, 43, 10, 30, 2007)],
            ['1960-10-30 02:59:43 Z', gmmktime(2, 59, 43, 10, 30, 1960)],
            ['1730-10-30T02:59:43Z', \PHP_INT_SIZE === 4 ? '-7547547617' : gmmktime(2, 59, 43, 10, 30, 1730)],

            ['"a \\"string\\" with \'quoted strings inside\'"', 'a "string" with \'quoted strings inside\''],
            ["'a \"string\" with ''quoted strings inside'''", 'a "string" with \'quoted strings inside\''],

            // sequences
            // urls are no key value mapping. see #3609. Valid yaml "key: value" mappings require a space after the colon
            ['[foo, http://urls.are/no/mappings, false, null, 12]', ['foo', 'http://urls.are/no/mappings', false, null, 12]],
            ['[  foo  ,   bar , false  ,  null     ,  12  ]', ['foo', 'bar', false, null, 12]],
            ['[\'foo,bar\', \'foo bar\']', ['foo,bar', 'foo bar']],

            // mappings
            ['{foo: bar,bar: foo,"false": false,"null": null,integer: 12}', (object) ['foo' => 'bar', 'bar' => 'foo', 'false' => false, 'null' => null, 'integer' => 12], Yaml::PARSE_OBJECT_FOR_MAP],
            ['{ foo  : bar, bar : foo,  "false"  :   false,  "null"  :   null,  integer :  12  }', (object) ['foo' => 'bar', 'bar' => 'foo', 'false' => false, 'null' => null, 'integer' => 12], Yaml::PARSE_OBJECT_FOR_MAP],
            ['{foo: \'bar\', bar: \'foo: bar\'}', (object) ['foo' => 'bar', 'bar' => 'foo: bar']],
            ['{\'foo\': \'bar\', "bar": \'foo: bar\'}', (object) ['foo' => 'bar', 'bar' => 'foo: bar']],
            ['{\'foo\'\'\': \'bar\', "bar\"": \'foo: bar\'}', (object) ['foo\'' => 'bar', 'bar"' => 'foo: bar']],
            ['{\'foo: \': \'bar\', "bar: ": \'foo: bar\'}', (object) ['foo: ' => 'bar', 'bar: ' => 'foo: bar']],
            ['{"foo:bar": "baz"}', (object) ['foo:bar' => 'baz']],
            ['{"foo":"bar"}', (object) ['foo' => 'bar']],

            // nested sequences and mappings
            ['[foo, [bar, foo]]', ['foo', ['bar', 'foo']]],
            ['[foo, {bar: foo}]', ['foo', (object) ['bar' => 'foo']]],
            ['{ foo: {bar: foo} }', (object) ['foo' => (object) ['bar' => 'foo']]],
            ['{ foo: [bar, foo] }', (object) ['foo' => ['bar', 'foo']]],

            ['[  foo, [  bar, foo  ]  ]', ['foo', ['bar', 'foo']]],

            ['[{ foo: {bar: foo} }]', [(object) ['foo' => (object) ['bar' => 'foo']]]],

            ['[foo, [bar, [foo, [bar, foo]], foo]]', ['foo', ['bar', ['foo', ['bar', 'foo']], 'foo']]],

            ['[foo, {bar: foo, foo: [foo, {bar: foo}]}, [foo, {bar: foo}]]', ['foo', (object) ['bar' => 'foo', 'foo' => ['foo', (object) ['bar' => 'foo']]], ['foo', (object) ['bar' => 'foo']]]],

            ['[foo, bar: { foo: bar }]', ['foo', '1' => (object) ['bar' => (object) ['foo' => 'bar']]]],
            ['[foo, \'@foo.baz\', { \'%foo%\': \'foo is %foo%\', bar: \'%foo%\' }, true, \'@service_container\']', ['foo', '@foo.baz', (object) ['%foo%' => 'foo is %foo%', 'bar' => '%foo%'], true, '@service_container']],

            ['{}', new \stdClass()],
            ['{ foo  : bar, bar : {}  }', (object) ['foo' => 'bar', 'bar' => new \stdClass()]],
            ['{ foo  : [], bar : {}  }', (object) ['foo' => [], 'bar' => new \stdClass()]],
            ['{foo: \'bar\', bar: {} }', (object) ['foo' => 'bar', 'bar' => new \stdClass()]],
            ['{\'foo\': \'bar\', "bar": {}}', (object) ['foo' => 'bar', 'bar' => new \stdClass()]],
            ['{\'foo\': \'bar\', "bar": \'{}\'}', (object) ['foo' => 'bar', 'bar' => '{}']],

            ['[foo, [{}, {}]]', ['foo', [new \stdClass(), new \stdClass()]]],
            ['[foo, [[], {}]]', ['foo', [[], new \stdClass()]]],
            ['[foo, [[{}, {}], {}]]', ['foo', [[new \stdClass(), new \stdClass()], new \stdClass()]]],
            ['[foo, {bar: {}}]', ['foo', '1' => (object) ['bar' => new \stdClass()]]],
        ];
    }

    public static function getTestsForDump()
    {
        return [
            ['null', null],
            ['false', false],
            ['true', true],
            ['12', 12],
            ["'1_2'", '1_2'],
            ['_12', '_12'],
            ["'12_'", '12_'],
            ["'quoted string'", 'quoted string'],
            ['1230.0', 12.30e+02],
            ['1.23E+45', 12.30e+44],
            ['1234', 0x4D2],
            ['1243', 02333],
            ["'0x_4_D_2_'", '0x_4_D_2_'],
            ["'0_2_3_3_3'", '0_2_3_3_3'],
            ['.Inf', -log(0)],
            ['-.Inf', log(0)],
            ["'686e444'", '686e444'],
            ['"foo\r\nbar"', "foo\r\nbar"],
            ["'foo#bar'", 'foo#bar'],
            ["'foo # bar'", 'foo # bar'],
            ["'#cfcfcf'", '#cfcfcf'],

            ["\"isn't it a nice single quote\"", "isn't it a nice single quote"],
            ['\'this is "double quoted"\'', 'this is "double quoted"'],
            ["\"one double, four single quotes: \\\"''''\"", 'one double, four single quotes: "\'\'\'\''],
            ['\'four double, one single quote: """"\'\'\'', 'four double, one single quote: """"\''],
            ["'a \"string\" with ''quoted strings inside'''", 'a "string" with \'quoted strings inside\''],

            ["'-dash'", '-dash'],
            ["'-'", '-'],

            // Pre-YAML-1.2 booleans
            ["'y'", 'y'],
            ["'n'", 'n'],
            ["'yes'", 'yes'],
            ["'no'", 'no'],
            ["'on'", 'on'],
            ["'off'", 'off'],

            // sequences
            ['[foo, bar, false, null, 12]', ['foo', 'bar', false, null, 12]],
            ['[\'foo,bar\', \'foo bar\']', ['foo,bar', 'foo bar']],

            // mappings
            ['{ foo: bar, bar: foo, \'false\': false, \'null\': null, integer: 12 }', ['foo' => 'bar', 'bar' => 'foo', 'false' => false, 'null' => null, 'integer' => 12]],
            ['{ foo: bar, bar: \'foo: bar\' }', ['foo' => 'bar', 'bar' => 'foo: bar']],

            // nested sequences and mappings
            ['[foo, [bar, foo]]', ['foo', ['bar', 'foo']]],

            ['[foo, [bar, [foo, [bar, foo]], foo]]', ['foo', ['bar', ['foo', ['bar', 'foo']], 'foo']]],

            ['{ foo: { bar: foo } }', ['foo' => ['bar' => 'foo']]],

            ['[foo, { bar: foo }]', ['foo', ['bar' => 'foo']]],

            ['[foo, { bar: foo, foo: [foo, { bar: foo }] }, [foo, { bar: foo }]]', ['foo', ['bar' => 'foo', 'foo' => ['foo', ['bar' => 'foo']]], ['foo', ['bar' => 'foo']]]],

            ['[foo, \'@foo.baz\', { \'%foo%\': \'foo is %foo%\', bar: \'%foo%\' }, true, \'@service_container\']', ['foo', '@foo.baz', ['%foo%' => 'foo is %foo%', 'bar' => '%foo%'], true, '@service_container']],

            ['{ foo: { bar: { 1: 2, baz: 3 } } }', ['foo' => ['bar' => [1 => 2, 'baz' => 3]]]],

            // numeric strings with trailing whitespaces
            ["'0123 '", '0123 '],
            ['"0123\f"', "0123\f"],
            ['"0123\n"', "0123\n"],
            ['"0123\r"', "0123\r"],
            ['"0123\t"', "0123\t"],
            ['"0123\v"', "0123\v"],

            // whitespaces
            'ideographic space' => ["'　'", '　'],
            'ideographic space surrounded by characters' => ["'a　b'", 'a　b'],
        ];
    }

    /**
     * @dataProvider getTimestampTests
     */
    public function testParseTimestampAsUnixTimestampByDefault(string $yaml, int $year, int $month, int $day, int $hour, int $minute, int $second, int $microsecond)
    {
        $expectedDate = (new \DateTimeImmutable($yaml, new \DateTimeZone('UTC')))->format('U');
        $this->assertSame($microsecond ? (float) "$expectedDate.$microsecond" : (int) $expectedDate, Inline::parse($yaml));
    }

    /**
     * @dataProvider getTimestampTests
     */
    public function testParseTimestampAsDateTimeObject(string $yaml, int $year, int $month, int $day, int $hour, int $minute, int $second, int $microsecond, string $timezone)
    {
        $expected = (new \DateTime($yaml))
            ->setTimeZone(new \DateTimeZone('UTC'))
            ->setDate($year, $month, $day)
            ->setTime($hour, $minute, $second, $microsecond);

        $date = Inline::parse($yaml, Yaml::PARSE_DATETIME);
        $this->assertEquals($expected, $date);
        $this->assertSame($timezone, $date->format('O'));
    }

    public static function getTimestampTests(): array
    {
        return [
            'canonical' => ['2001-12-15T02:59:43.1Z', 2001, 12, 15, 2, 59, 43, 100000, '+0000'],
            'ISO-8601' => ['2001-12-15t21:59:43.10-05:00', 2001, 12, 16, 2, 59, 43, 100000, '-0500'],
            'spaced' => ['2001-12-15 21:59:43.10 -5', 2001, 12, 16, 2, 59, 43, 100000, '-0500'],
            'date' => ['2001-12-15', 2001, 12, 15, 0, 0, 0, 0, '+0000'],
        ];
    }

    /**
     * @dataProvider getTimestampTests
     */
    public function testParseNestedTimestampListAsDateTimeObject(string $yaml, int $year, int $month, int $day, int $hour, int $minute, int $second, int $microsecond)
    {
        $expected = (new \DateTime($yaml))
            ->setTimeZone(new \DateTimeZone('UTC'))
            ->setDate($year, $month, $day)
            ->setTime($hour, $minute, $second, $microsecond);

        $expectedNested = ['nested' => [$expected]];
        $yamlNested = "{nested: [$yaml]}";

        $this->assertEquals($expectedNested, Inline::parse($yamlNested, Yaml::PARSE_DATETIME));
    }

    /**
     * @dataProvider getDateTimeDumpTests
     */
    public function testDumpDateTime($dateTime, $expected)
    {
        $this->assertSame($expected, Inline::dump($dateTime));
    }

    /**
     * @dataProvider getNumericKeyData
     */
    public function testDumpNumericKeyAsString(array|int $input, int $flags, string $expected)
    {
        $this->assertSame($expected, Inline::dump($input, $flags));
    }

    public static function getNumericKeyData()
    {
        yield 'Int with flag' => [
            200,
            Yaml::DUMP_NUMERIC_KEY_AS_STRING,
            '200',
        ];

        yield 'Int key with flag' => [
            [200 => 'foo'],
            Yaml::DUMP_NUMERIC_KEY_AS_STRING,
            "{ '200': foo }",
        ];

        yield 'Int value with flag' => [
            [200 => 200],
            Yaml::DUMP_NUMERIC_KEY_AS_STRING,
            "{ '200': 200 }",
        ];

        yield 'String key with flag' => [
            ['200' => 'foo'],
            Yaml::DUMP_NUMERIC_KEY_AS_STRING,
            "{ '200': foo }",
        ];

        yield 'Mixed with flag' => [
            [42 => 'a', 'b' => 'c', 'd' => 43],
            Yaml::DUMP_NUMERIC_KEY_AS_STRING,
            "{ '42': a, b: c, d: 43 }",
        ];

        yield 'Auto-index with flag' => [
            ['a', 'b', 42],
            Yaml::DUMP_NUMERIC_KEY_AS_STRING,
            '[a, b, 42]',
        ];

        yield 'Complex mixed array with flag' => [
            [
                42 => [
                    'foo' => 43,
                    44 => 'bar',
                ],
                45 => 'baz',
                46,
            ],
            Yaml::DUMP_NUMERIC_KEY_AS_STRING,
            "{ '42': { foo: 43, '44': bar }, '45': baz, '46': 46 }",
        ];

        yield 'Int tagged value with flag' => [
            [
                'count' => new TaggedValue('number', 5),
            ],
            Yaml::DUMP_NUMERIC_KEY_AS_STRING,
            '{ count: !number 5 }',
        ];

        yield 'Array tagged value with flag' => [
            [
                'user' => new TaggedValue('metadata', [
                    'john',
                    42,
                ]),
            ],
            Yaml::DUMP_NUMERIC_KEY_AS_STRING,
            '{ user: !metadata [john, 42] }',
        ];

        $arrayObject = new \ArrayObject();
        $arrayObject['foo'] = 'bar';
        $arrayObject[42] = 'baz';
        $arrayObject['baz'] = 43;

        yield 'Object value with flag' => [
            [
                'user' => $arrayObject,
            ],
            Yaml::DUMP_NUMERIC_KEY_AS_STRING | Yaml::DUMP_OBJECT_AS_MAP,
            "{ user: { foo: bar, '42': baz, baz: 43 } }",
        ];
    }

    public function testDumpUnitEnum()
    {
        $this->assertSame("!php/const Symfony\Component\Yaml\Tests\Fixtures\FooUnitEnum::BAR", Inline::dump(FooUnitEnum::BAR));
    }

    public function testParseUnitEnum()
    {
        $this->assertSame(FooUnitEnum::BAR, Inline::parse("!php/enum Symfony\Component\Yaml\Tests\Fixtures\FooUnitEnum::BAR", Yaml::PARSE_CONSTANT));
    }

    public function testParseBackedEnumValue()
    {
        $this->assertSame(FooBackedEnum::BAR->value, Inline::parse("!php/enum Symfony\Component\Yaml\Tests\Fixtures\FooBackedEnum::BAR->value", Yaml::PARSE_CONSTANT));
    }

    public static function getDateTimeDumpTests()
    {
        $tests = [];

        $dateTime = new \DateTimeImmutable('2001-12-15 21:59:43', new \DateTimeZone('UTC'));
        $tests['date-time-utc'] = [$dateTime, '2001-12-15T21:59:43+00:00'];

        $dateTime = new \DateTimeImmutable('2001-07-15 21:59:43', new \DateTimeZone('Europe/Berlin'));
        $tests['immutable-date-time-europe-berlin'] = [$dateTime, '2001-07-15T21:59:43+02:00'];

        return $tests;
    }

    /**
     * @dataProvider getBinaryData
     */
    public function testParseBinaryData($data)
    {
        $this->assertSame('Hello world', Inline::parse($data));
    }

    public static function getBinaryData()
    {
        return [
            'enclosed with double quotes' => ['!!binary "SGVsbG8gd29ybGQ="'],
            'enclosed with single quotes' => ["!!binary 'SGVsbG8gd29ybGQ='"],
            'containing spaces' => ['!!binary  "SGVs bG8gd 29ybGQ="'],
        ];
    }

    /**
     * @dataProvider getInvalidBinaryData
     */
    public function testParseInvalidBinaryData($data, $expectedMessage)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessageMatches($expectedMessage);

        Inline::parse($data);
    }

    public static function getInvalidBinaryData()
    {
        return [
            'length not a multiple of four' => ['!!binary "SGVsbG8d29ybGQ="', '/The normalized base64 encoded data \(data without whitespace characters\) length must be a multiple of four \(\d+ bytes given\)/'],
            'invalid characters' => ['!!binary "SGVsbG8#d29ybGQ="', '/The base64 encoded data \(.*\) contains invalid characters/'],
            'too many equals characters' => ['!!binary "SGVsbG8gd29yb==="', '/The base64 encoded data \(.*\) contains invalid characters/'],
            'misplaced equals character' => ['!!binary "SGVsbG8gd29ybG=Q"', '/The base64 encoded data \(.*\) contains invalid characters/'],
        ];
    }

    public function testNotSupportedMissingValue()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Malformed inline YAML string: "{this, is not, supported}" at line 1.');
        Inline::parse('{this, is not, supported}');
    }

    public function testVeryLongQuotedStrings()
    {
        $longStringWithQuotes = str_repeat("x\r\n\\\"x\"x", 1000);

        $yamlString = Inline::dump(['longStringWithQuotes' => $longStringWithQuotes]);
        $arrayFromYaml = Inline::parse($yamlString);

        $this->assertEquals($longStringWithQuotes, $arrayFromYaml['longStringWithQuotes']);
    }

    public function testMappingKeysCannotBeOmitted()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Missing mapping key');
        Inline::parse('{: foo}');
    }

    /**
     * @dataProvider getTestsForNullValues
     */
    public function testParseMissingMappingValueAsNull($yaml, $expected)
    {
        $this->assertSame($expected, Inline::parse($yaml));
    }

    public static function getTestsForNullValues()
    {
        return [
            'null before closing curly brace' => ['{foo:}', ['foo' => null]],
            'null before comma' => ['{foo:, bar: baz}', ['foo' => null, 'bar' => 'baz']],
        ];
    }

    public function testTheEmptyStringIsAValidMappingKey()
    {
        $this->assertSame(['' => 'foo'], Inline::parse('{ "": foo }'));
    }

    /**
     * @dataProvider getNotPhpCompatibleMappingKeyData
     */
    public function testImplicitStringCastingOfMappingKeysIsDeprecated($yaml, $expected)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Implicit casting of incompatible mapping keys to strings is not supported. Quote your evaluable mapping keys instead');
        $this->assertSame($expected, Inline::parse($yaml));
    }

    public static function getNotPhpCompatibleMappingKeyData()
    {
        return [
            'boolean-true' => ['{true: "foo"}', ['true' => 'foo']],
            'boolean-false' => ['{false: "foo"}', ['false' => 'foo']],
            'null' => ['{null: "foo"}', ['null' => 'foo']],
            'float' => ['{0.25: "foo"}', ['0.25' => 'foo']],
        ];
    }

    public function testTagWithoutValueInSequence()
    {
        $value = Inline::parse('[!foo]', Yaml::PARSE_CUSTOM_TAGS);

        $this->assertInstanceOf(TaggedValue::class, $value[0]);
        $this->assertSame('foo', $value[0]->getTag());
        $this->assertSame('', $value[0]->getValue());
    }

    public function testTagWithEmptyValueInSequence()
    {
        $value = Inline::parse('[!foo ""]', Yaml::PARSE_CUSTOM_TAGS);

        $this->assertInstanceOf(TaggedValue::class, $value[0]);
        $this->assertSame('foo', $value[0]->getTag());
        $this->assertSame('', $value[0]->getValue());
    }

    public function testTagWithoutValueInMapping()
    {
        $value = Inline::parse('{foo: !bar}', Yaml::PARSE_CUSTOM_TAGS);

        $this->assertInstanceOf(TaggedValue::class, $value['foo']);
        $this->assertSame('bar', $value['foo']->getTag());
        $this->assertSame('', $value['foo']->getValue());
    }

    public function testTagWithEmptyValueInMapping()
    {
        $value = Inline::parse('{foo: !bar ""}', Yaml::PARSE_CUSTOM_TAGS);

        $this->assertInstanceOf(TaggedValue::class, $value['foo']);
        $this->assertSame('bar', $value['foo']->getTag());
        $this->assertSame('', $value['foo']->getValue());
    }

    public function testTagWithQuotedInteger()
    {
        $value = Inline::parse('!number "5"', Yaml::PARSE_CUSTOM_TAGS);

        $this->assertInstanceOf(TaggedValue::class, $value);
        $this->assertSame('number', $value->getTag());
        $this->assertSame('5', $value->getValue());
    }

    public function testTagWithUnquotedInteger()
    {
        $value = Inline::parse('!number 5', Yaml::PARSE_CUSTOM_TAGS);

        $this->assertInstanceOf(TaggedValue::class, $value);
        $this->assertSame('number', $value->getTag());
        $this->assertSame(5, $value->getValue());
    }

    public function testUnfinishedInlineMap()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage("Unexpected end of line, expected one of \",}\n\" at line 1 (near \"{abc: 'def'\").");
        Inline::parse("{abc: 'def'");
    }

    /**
     * @dataProvider getTestsForOctalNumbers
     */
    public function testParseOctalNumbers($expected, $yaml)
    {
        self::assertSame($expected, Inline::parse($yaml));
    }

    public static function getTestsForOctalNumbers()
    {
        return [
            'positive octal number' => [28, '0o34'],
            'positive octal number with sign' => [28, '+0o34'],
            'negative octal number' => [-28, '-0o34'],
        ];
    }

    /**
     * @dataProvider getTestsForOctalNumbersYaml11Notation
     */
    public function testParseOctalNumbersYaml11Notation(string $expected, string $yaml)
    {
        self::assertSame($expected, Inline::parse($yaml));
    }

    public static function getTestsForOctalNumbersYaml11Notation()
    {
        return [
            'positive octal number' => ['034', '034'],
            'positive octal number with separator' => ['02333', '0_2_3_3_3'],
            'negative octal number' => ['-034', '-034'],
            'invalid positive octal number' => ['0123456789', '0123456789'],
            'invalid negative octal number' => ['-0123456789', '-0123456789'],
        ];
    }

    /**
     * @dataProvider phpObjectTagWithEmptyValueProvider
     */
    public function testPhpObjectWithEmptyValue(string $value)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Missing value for tag "!php/object" at line 1 (near "!php/object").');

        Inline::parse($value, Yaml::PARSE_OBJECT);
    }

    public static function phpObjectTagWithEmptyValueProvider()
    {
        return [
            ['!php/object'],
            ['!php/object '],
            ['!php/object  '],
            ['[!php/object]'],
            ['[!php/object ]'],
            ['[!php/object  , foo]'],
        ];
    }

    /**
     * @dataProvider phpConstTagWithEmptyValueProvider
     */
    public function testPhpConstTagWithEmptyValue(string $value)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Missing value for tag "!php/const" at line 1 (near "!php/const").');

        Inline::parse($value, Yaml::PARSE_CONSTANT);
    }

    /**
     * @dataProvider phpConstTagWithEmptyValueProvider
     */
    public function testPhpEnumTagWithEmptyValue(string $value)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Missing value for tag "!php/enum" at line 1 (near "!php/enum").');

        Inline::parse(str_replace('!php/const', '!php/enum', $value), Yaml::PARSE_CONSTANT);
    }

    public static function phpConstTagWithEmptyValueProvider()
    {
        return [
            ['!php/const'],
            ['!php/const '],
            ['!php/const  '],
            ['[!php/const]'],
            ['[!php/const ]'],
            ['[!php/const  , foo]'],
            ['{!php/const: foo}'],
            ['{!php/const : foo}'],
            ['{!php/const  : foo, bar: ccc}'],
        ];
    }

    public function testParseCommentNotPrefixedBySpaces()
    {
        self::assertSame('foo', Inline::parse('"foo"#comment'));
    }

    public function testParseUnquotedStringContainingHashTagNotPrefixedBySpace()
    {
        self::assertSame('foo#nocomment', Inline::parse('foo#nocomment'));
    }

    /**
     * @dataProvider unquotedExclamationMarkThrowsProvider
     */
    public function testUnquotedExclamationMarkThrows(string $value)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessageMatches('/^Using the unquoted scalar value "!" is not supported\. You must quote it at line 1 \(near "/');

        Inline::parse($value);
    }

    public static function unquotedExclamationMarkThrowsProvider()
    {
        return [
            ['!'],
            ['! '],
            ['!  '],
            [' ! '],
            ['[!]'],
            ['[! ]'],
            ['[!  ]'],
            ['[!, "foo"]'],
            ['["foo", !, "ccc"]'],
            ['{foo: !}'],
            ['{foo:     !}'],
            ['{foo: !, bar: "ccc"}'],
            ['{bar: "ccc", foo: ! }'],
            ['!]]]'],
            ['!}'],
            ['!,}foo,]'],
            ['! [!]'],
        ];
    }

    /**
     * @dataProvider quotedExclamationMarkProvider
     */
    public function testQuotedExclamationMark($expected, string $value)
    {
        $this->assertSame($expected, Inline::parse($value));
    }

    // This provider should stay consistent with unquotedExclamationMarkThrowsProvider
    public static function quotedExclamationMarkProvider()
    {
        return [
            ['!', '"!"'],
            ['! ', '"! "'],
            [' !', '" !"'],
            [' ! ', '" ! "'],
            [['!'], '["!"]'],
            [['!  '], '["!  "]'],
            [['!', 'foo'], '["!", "foo"]'],
            [['foo', '!', 'ccc'], '["foo", "!", "ccc"]'],
            [['foo' => '!'], '{foo: "!"}'],
            [['foo' => '    !'], '{foo: "    !"}'],
            [['foo' => '!', 'bar' => 'ccc'], '{foo: "!", bar: "ccc"}'],
            [['bar' => 'ccc', 'foo' => '! '], '{bar: "ccc", foo: "! "}'],
            ['!]]]', '"!]]]"'],
            ['!}', '"!}"'],
            ['!,}foo,]', '"!,}foo,]"'],
            [['!'], '! ["!"]'],
        ];
    }

    /**
     * @dataProvider ideographicSpaceProvider
     */
    public function testParseIdeographicSpace(string $yaml, string $expected)
    {
        $this->assertSame($expected, Inline::parse($yaml));
    }

    public static function ideographicSpaceProvider(): array
    {
        return [
            ["\u{3000}", '　'],
            ["'\u{3000}'", '　'],
            ["'a　b'", 'a　b'],
        ];
    }

    public function testParseSingleQuotedTaggedString()
    {
        $this->assertSame('foo', Inline::parse("!!str 'foo'"));
    }

    public function testParseDoubleQuotedTaggedString()
    {
        $this->assertSame('foo', Inline::parse('!!str "foo"'));
    }

    public function testParseQuotedReferenceLikeStringsInMapping()
    {
        $yaml = <<<YAML
{foo: '&foo', bar: "&bar", baz: !!str '&baz'}
YAML;

        $this->assertSame(['foo' => '&foo', 'bar' => '&bar', 'baz' => '&baz'], Inline::parse($yaml));
    }

    public function testParseQuotedReferenceLikeStringsInSequence()
    {
        $yaml = <<<YAML
['&foo', "&bar", !!str '&baz']
YAML;

        $this->assertSame(['&foo', '&bar', '&baz'], Inline::parse($yaml));
    }
}
