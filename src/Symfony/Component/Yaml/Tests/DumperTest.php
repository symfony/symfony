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

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;

class DumperTest extends \PHPUnit_Framework_TestCase
{
    protected $parser;
    protected $dumper;
    protected $path;

    protected $array = array(
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
        $this->array = null;
    }

    public function testSetIndentation()
    {
        $this->dumper->setIndentation(7);

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
        $this->assertEquals($expected, $this->dumper->dump($this->array, 4, 0));
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
                    eval('$expected = '.trim($test['php']).';');

                    $this->assertEquals($expected, $this->parser->parse($this->dumper->dump($expected, 10)), $test['test']);
                }
            }
        }
    }

    public function testInlineLevel()
    {
        $expected = <<<EOF
{ '': bar, foo: '#bar', 'foo''bar': {  }, bar: [1, foo], foobar: { foo: bar, bar: [1, foo], foobar: { foo: bar, bar: [1, foo] } } }
EOF;
        $this->assertEquals($expected, $this->dumper->dump($this->array, -10), '->dump() takes an inline level argument');
        $this->assertEquals($expected, $this->dumper->dump($this->array, 0), '->dump() takes an inline level argument');

        $expected = <<<EOF
'': bar
foo: '#bar'
'foo''bar': {  }
bar: [1, foo]
foobar: { foo: bar, bar: [1, foo], foobar: { foo: bar, bar: [1, foo] } }

EOF;
        $this->assertEquals($expected, $this->dumper->dump($this->array, 1), '->dump() takes an inline level argument');

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
        $this->assertEquals($expected, $this->dumper->dump($this->array, 2), '->dump() takes an inline level argument');

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
        $this->assertEquals($expected, $this->dumper->dump($this->array, 3), '->dump() takes an inline level argument');

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
        $this->assertEquals($expected, $this->dumper->dump($this->array, 4), '->dump() takes an inline level argument');
        $this->assertEquals($expected, $this->dumper->dump($this->array, 10), '->dump() takes an inline level argument');
    }

    public function testObjectSupportEnabled()
    {
        $dump = $this->dumper->dump(array('foo' => new A(), 'bar' => 1), 0, 0, false, true);

        $this->assertEquals('{ foo: !!php/object:O:30:"Symfony\Component\Yaml\Tests\A":1:{s:1:"a";s:3:"foo";}, bar: 1 }', $dump, '->dump() is able to dump objects');
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

    /**
     * @dataProvider getEscapeSequences
     */
    public function testEscapedEscapeSequencesInQuotedScalar($input, $expected)
    {
        $this->assertEquals($expected, $this->dumper->dump($input));
    }

    public function getEscapeSequences()
    {
        return array(
            'null' => array("\t\\0", '"\t\\\\0"'),
            'bell' => array("\t\\a", '"\t\\\\a"'),
            'backspace' => array("\t\\b", '"\t\\\\b"'),
            'horizontal-tab' => array("\t\\t", '"\t\\\\t"'),
            'line-feed' => array("\t\\n", '"\t\\\\n"'),
            'vertical-tab' => array("\t\\v", '"\t\\\\v"'),
            'form-feed' => array("\t\\f", '"\t\\\\f"'),
            'carriage-return' => array("\t\\r", '"\t\\\\r"'),
            'escape' => array("\t\\e", '"\t\\\\e"'),
            'space' => array("\t\\ ", '"\t\\\\ "'),
            'double-quote' => array("\t\\\"", '"\t\\\\\\""'),
            'slash' => array("\t\\/", '"\t\\\\/"'),
            'backslash' => array("\t\\\\", '"\t\\\\\\\\"'),
            'next-line' => array("\t\\N", '"\t\\\\N"'),
            'non-breaking-space' => array("\t\\�", '"\t\\\\�"'),
            'line-separator' => array("\t\\L", '"\t\\\\L"'),
            'paragraph-separator' => array("\t\\P", '"\t\\\\P"'),
        );
    }
}

class A
{
    public $a = 'foo';
}
