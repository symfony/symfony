<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\YAML\YAML;
use Symfony\Components\YAML\Inline;

YAML::setSpecVersion('1.1');

$t = new LimeTest(124);

// ::load()
$t->diag('::load()');

$testsForLoad = array(
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

foreach ($testsForLoad as $yaml => $value)
{
  $t->is(Inline::load($yaml), $value, sprintf('::load() converts an inline YAML to a PHP structure (%s)', $yaml));
}

$testsForDump = array(
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

// ::dump()
$t->diag('::dump()');
foreach ($testsForDump as $yaml => $value)
{
  $t->is(Inline::dump($value), $yaml, sprintf('::dump() converts a PHP structure to an inline YAML (%s)', $yaml));
}

foreach ($testsForLoad as $yaml => $value)
{
  if ($value == 1230)
  {
    continue;
  }

  $t->is(Inline::load(Inline::dump($value)), $value, 'check consistency');
}

foreach ($testsForDump as $yaml => $value)
{
  if ($value == 1230)
  {
    continue;
  }

  $t->is(Inline::load(Inline::dump($value)), $value, 'check consistency');
}
