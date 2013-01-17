<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Yaml;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;

class DumperTest extends \PHPUnit_Framework_TestCase
{
    protected $parser;
    protected $dumper;
    protected $path;

    protected function setUp()
    {
        $this->parser = new Parser();
        $this->dumper = new Dumper();
        $this->path = __DIR__.'/Fixtures';
    }

    protected function tearDown()
    {
        $this->parser = null;
        $this->dumper = null;
        $this->path = null;
    }

    public function testSpecifications()
    {
        $files = $this->parser->parse(file_get_contents($this->path.'/index.yml'));
        foreach ($files as $file) {
            $yamls = file_get_contents($this->path.'/'.$file.'.yml');

            // split YAMLs documents
            foreach (preg_split('/^---( %YAML\:1\.0)?/m', $yamls) as $yaml) {
                if (!$yaml) {
                    continue;
                }

                $test = $this->parser->parse($yaml);
                if (isset($test['dump_skip']) && $test['dump_skip']) {
                    continue;
                } elseif (isset($test['todo']) && $test['todo']) {
                    // TODO
                } else {
                    $expected = eval('return '.trim($test['php']).';');

                    $this->assertEquals($expected, $this->parser->parse($this->dumper->dump($expected, 10)), $test['test']);
                }
            }
        }
    }

    public function testInlineLevel()
    {
        // inline level
        $array = array(
            '' => 'bar',
            'foo' => '#bar',
            'foo\'bar' => array(),
            'bar' => array(1, 'foo'),
            'foobar' => array(
                'foo' => 'bar',
                'bar' => array(1, 'foo'),
                'foobar' => array(
                    'foo' => 'bar',
                    'bar' => array(1, 'foo'),
                ),
            ),
        );

        $expected = <<<EOF
{ '': bar, foo: '#bar', 'foo''bar': {  }, bar: [1, foo], foobar: { foo: bar, bar: [1, foo], foobar: { foo: bar, bar: [1, foo] } } }
EOF;
$this->assertEquals($expected, $this->dumper->dump($array, -10), '->dump() takes an inline level argument');
$this->assertEquals($expected, $this->dumper->dump($array, 0), '->dump() takes an inline level argument');

$expected = <<<EOF
'': bar
foo: '#bar'
'foo''bar': {  }
bar: [1, foo]
foobar: { foo: bar, bar: [1, foo], foobar: { foo: bar, bar: [1, foo] } }

EOF;
        $this->assertEquals($expected, $this->dumper->dump($array, 1), '->dump() takes an inline level argument');

        $expected = <<<EOF
'': bar
foo: '#bar'
'foo''bar': {  }
bar:
  - 1
  - foo
foobar:
  foo: bar
  bar: [1, foo]
  foobar: { foo: bar, bar: [1, foo] }

EOF;
        $this->assertEquals($expected, $this->dumper->dump($array, 2), '->dump() takes an inline level argument');

        $expected = <<<EOF
'': bar
foo: '#bar'
'foo''bar': {  }
bar:
  - 1
  - foo
foobar:
  foo: bar
  bar:
    - 1
    - foo
  foobar:
    foo: bar
    bar: [1, foo]

EOF;
        $this->assertEquals($expected, $this->dumper->dump($array, 3), '->dump() takes an inline level argument');

        $expected = <<<EOF
'': bar
foo: '#bar'
'foo''bar': {  }
bar:
  - 1
  - foo
foobar:
  foo: bar
  bar:
    - 1
    - foo
  foobar:
    foo: bar
    bar:
      - 1
      - foo

EOF;
        $this->assertEquals($expected, $this->dumper->dump($array, 4), '->dump() takes an inline level argument');
        $this->assertEquals($expected, $this->dumper->dump($array, 10), '->dump() takes an inline level argument');
    }

    public function testObjectSupportEnabled()
    {
        $dump = $this->dumper->dump(array('foo' => new A(), 'bar' => 1), 0, 0, false, true);

        $this->assertEquals('{ foo: !!php/object:O:30:"Symfony\Tests\Component\Yaml\A":1:{s:1:"a";s:3:"foo";}, bar: 1 }', $dump, '->dump() is able to dump objects');
    }

    public function testObjectSupportDisabledButNoExceptions()
    {
        $dump = $this->dumper->dump(array('foo' => new A(), 'bar' => 1));

        $this->assertEquals('{ foo: null, bar: 1 }', $dump, '->dump() does not dump objects when disabled');
    }

    /**
     * @expectedException \Symfony\Component\Yaml\Exception\DumpException
     */
    public function testObjectSupportDisabledWithExceptions()
    {
        $this->dumper->dump(array('foo' => new A(), 'bar' => 1), 0, 0, true, false);
    }
}

class A
{
    public $a = 'foo';
}
