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

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Yaml;

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

    public function testIndentationInConstructor()
    {
        $dumper = new Dumper(7);
        $expected = <<<'EOF'
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
        $this->assertEquals($expected, $dumper->dump($this->array, 4, 0));
    }

    /**
     * @group legacy
     */
    public function testSetIndentation()
    {
        $this->dumper->setIndentation(7);

        $expected = <<<'EOF'
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
                    $this->assertSame($expected, $this->parser->parse($this->dumper->dump($expected, 10)), $test['test']);
                }
            }
        }
    }

    public function testInlineLevel()
    {
        $expected = <<<'EOF'
{ '': bar, foo: '#bar', 'foo''bar': {  }, bar: [1, foo], foobar: { foo: bar, bar: [1, foo], foobar: { foo: bar, bar: [1, foo] } } }
EOF;
        $this->assertEquals($expected, $this->dumper->dump($this->array, -10), '->dump() takes an inline level argument');
        $this->assertEquals($expected, $this->dumper->dump($this->array, 0), '->dump() takes an inline level argument');

        $expected = <<<'EOF'
'': bar
foo: '#bar'
'foo''bar': {  }
bar: [1, foo]
foobar: { foo: bar, bar: [1, foo], foobar: { foo: bar, bar: [1, foo] } }

EOF;
        $this->assertEquals($expected, $this->dumper->dump($this->array, 1), '->dump() takes an inline level argument');

        $expected = <<<'EOF'
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

        $expected = <<<'EOF'
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

        $expected = <<<'EOF'
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
        $dump = $this->dumper->dump(array('foo' => new A(), 'bar' => 1), 0, 0, Yaml::DUMP_OBJECT);

        $this->assertEquals('{ foo: !php/object:O:30:"Symfony\Component\Yaml\Tests\A":1:{s:1:"a";s:3:"foo";}, bar: 1 }', $dump, '->dump() is able to dump objects');
    }

    /**
     * @group legacy
     */
    public function testObjectSupportEnabledPassingTrue()
    {
        $dump = $this->dumper->dump(array('foo' => new A(), 'bar' => 1), 0, 0, false, true);

        $this->assertEquals('{ foo: !php/object:O:30:"Symfony\Component\Yaml\Tests\A":1:{s:1:"a";s:3:"foo";}, bar: 1 }', $dump, '->dump() is able to dump objects');
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
        $this->dumper->dump(array('foo' => new A(), 'bar' => 1), 0, 0, Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE);
    }

    /**
     * @group legacy
     * @expectedException \Symfony\Component\Yaml\Exception\DumpException
     */
    public function testObjectSupportDisabledWithExceptionsPassingTrue()
    {
        $this->dumper->dump(array('foo' => new A(), 'bar' => 1), 0, 0, true);
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

    public function testBinaryDataIsDumpedBase64Encoded()
    {
        $binaryData = file_get_contents(__DIR__.'/Fixtures/arrow.gif');
        $expected = '{ data: !!binary '.base64_encode($binaryData).' }';

        $this->assertSame($expected, $this->dumper->dump(array('data' => $binaryData)));
    }

    public function testNonUtf8DataIsDumpedBase64Encoded()
    {
        // "für" (ISO-8859-1 encoded)
        $this->assertSame('!!binary ZsM/cg==', $this->dumper->dump("f\xc3\x3fr"));
    }

    /**
     * @dataProvider objectAsMapProvider
     */
    public function testDumpObjectAsMap($object, $expected)
    {

        $yaml = $this->dumper->dump($object, 0, 0, Yaml::DUMP_OBJECT_AS_MAP);

        $this->assertEquals($expected, Yaml::parse($yaml, Yaml::PARSE_OBJECT_FOR_MAP));
    }

    public function objectAsMapProvider()
    {
        $tests = array();

        $bar = new \stdClass();
        $bar->class = 'classBar';
        $bar->args = array('bar');
        $zar = new \stdClass();
        $foo = new \stdClass();
        $foo->bar = $bar;
        $foo->zar = $zar;
        $object = new \stdClass();
        $object->foo = $foo;
        $tests['stdClass'] = array($object, $object);

        $arrayObject = new \ArrayObject();
        $arrayObject['foo'] = 'bar';
        $arrayObject['baz'] = 'foobar';
        $parsedArrayObject = new \stdClass();
        $parsedArrayObject->foo = 'bar';
        $parsedArrayObject->baz = 'foobar';
        $tests['ArrayObject'] = array($arrayObject, $parsedArrayObject);

        $a = new A();
        $tests['arbitrary-object'] = array($a, null);

        return $tests;
    }

    public function testDumpMultiLineStringAsScalarBlock()
    {
        $data = array(
            'data' => array(
                'single_line' => 'foo bar baz',
                'multi_line' => "foo\nline with trailing spaces:\n  \nbar\r\ninteger like line:\n123456789\nempty line:\n\nbaz",
                'nested_inlined_multi_line_string' => array(
                    'inlined_multi_line' => "foo\nbar\r\nempty line:\n\nbaz",
                ),
            ),
        );

        $this->assertSame(file_get_contents(__DIR__.'/Fixtures/multiple_lines_as_literal_block.yml'), $this->dumper->dump($data, 3, 0, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The indentation must be greater than zero
     */
    public function testZeroIndentationThrowsException()
    {
        new Dumper(0);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The indentation must be greater than zero
     */
    public function testNegativeIndentationThrowsException()
    {
        new Dumper(-4);
    }
}

class A
{
    public $a = 'foo';
}
