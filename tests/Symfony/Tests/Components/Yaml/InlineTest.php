<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Yaml;

use Symfony\Components\Yaml\Yaml;
use Symfony\Components\Yaml\Inline;

class InlineTest extends \PHPUnit_Framework_TestCase
{
    static public function setUpBeforeClass()
    {
        Yaml::setSpecVersion('1.1');
    }

    public function testLoad()
    {
        foreach ($this->getTestsForLoad() as $yaml => $value) {
            $this->assertEquals($value, Inline::load($yaml), sprintf('::load() converts an inline YAML to a PHP structure (%s)', $yaml));
        }
    }

    public function testDump()
    {
        $testsForDump = $this->getTestsForDump();

        foreach ($testsForDump as $yaml => $value) {
            $this->assertEquals($yaml, Inline::dump($value), sprintf('::dump() converts a PHP structure to an inline YAML (%s)', $yaml));
        }

        foreach ($this->getTestsForLoad() as $yaml => $value) {
            if ($value == 1230) {
                continue;
            }

            $this->assertEquals($value, Inline::load(Inline::dump($value)), 'check consistency');
        }

        foreach ($testsForDump as $yaml => $value) {
            if ($value == 1230) {
                continue;
            }

            $this->assertEquals($value, Inline::load(Inline::dump($value)), 'check consistency');
        }
    }

    protected function getTestsForLoad()
    {
        return array(
            '' => '',
            'null' => null,
            'false' => false,
            'true' => true,
            '12' => 12,
            '"quoted string"' => 'quoted string',
            "'quoted string'" => 'quoted string',
            '12.30e+02' => 12.30e+02,
            '0x4D2' => 0x4D2,
            '02333' => 02333,
            '.Inf' => -log(0),
            '-.Inf' => log(0),
            '123456789123456789' => '123456789123456789',
            '"foo\r\nbar"' => "foo\r\nbar",
            "'foo#bar'" => 'foo#bar',
            "'foo # bar'" => 'foo # bar',
            "'#cfcfcf'" => '#cfcfcf',

            '2007-10-30' => mktime(0, 0, 0, 10, 30, 2007),
            '2007-10-30T02:59:43Z' => gmmktime(2, 59, 43, 10, 30, 2007),
            '2007-10-30 02:59:43 Z' => gmmktime(2, 59, 43, 10, 30, 2007),

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
            '"foo\r\nbar"' => "foo\r\nbar",
            "'foo#bar'" => 'foo#bar',
            "'foo # bar'" => 'foo # bar',
            "'#cfcfcf'" => '#cfcfcf',

            "'a \"string\" with ''quoted strings inside'''" => 'a "string" with \'quoted strings inside\'',

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
        );
    }
}
