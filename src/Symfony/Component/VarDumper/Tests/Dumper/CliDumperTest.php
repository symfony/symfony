<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class CliDumperTest extends TestCase
{
    use VarDumperTestTrait;

    public function testGet()
    {
        require __DIR__.'/../Fixtures/dumb-var.php';

        $dumper = new CliDumper('php://output');
        $dumper->setColors(false);
        $cloner = new VarCloner();
        $cloner->addCasters(array(
            ':stream' => function ($res, $a) {
                unset($a['uri'], $a['wrapper_data']);

                return $a;
            },
        ));
        $data = $cloner->cloneVar($var);

        ob_start();
        $dumper->dump($data);
        $out = ob_get_clean();
        $out = preg_replace('/[ \t]+$/m', '', $out);
        $intMax = PHP_INT_MAX;
        $res = (int) $var['res'];

        $this->assertStringMatchesFormat(
            <<<EOTXT
array:24 [
  "number" => 1
  0 => &1 null
  "const" => 1.1
  1 => true
  2 => false
  3 => NAN
  4 => INF
  5 => -INF
  6 => {$intMax}
  "str" => "déjà\\n"
  7 => b"é\\x00"
  "[]" => []
  "res" => stream resource {@{$res}
%A  wrapper_type: "plainfile"
    stream_type: "STDIO"
    mode: "r"
    unread_bytes: 0
    seekable: true
%A  options: []
  }
  "obj" => Symfony\Component\VarDumper\Tests\Fixture\DumbFoo {#%d
    +foo: "foo"
    +"bar": "bar"
  }
  "closure" => Closure {#%d
    class: "Symfony\Component\VarDumper\Tests\Dumper\CliDumperTest"
    this: Symfony\Component\VarDumper\Tests\Dumper\CliDumperTest {#%d …}
    parameters: {
      \$a: {}
      &\$b: {
        typeHint: "PDO"
        default: null
      }
    }
    file: "%s%eTests%eFixtures%edumb-var.php"
    line: "{$var['line']} to {$var['line']}"
  }
  "line" => {$var['line']}
  "nobj" => array:1 [
    0 => &3 {#%d}
  ]
  "recurs" => &4 array:1 [
    0 => &4 array:1 [&4]
  ]
  8 => &1 null
  "sobj" => Symfony\Component\VarDumper\Tests\Fixture\DumbFoo {#%d}
  "snobj" => &3 {#%d}
  "snobj2" => {#%d}
  "file" => "{$var['file']}"
  b"bin-key-é" => ""
]

EOTXT
            ,
            $out
        );
    }

    /**
     * @dataProvider provideDumpWithCommaFlagTests
     */
    public function testDumpWithCommaFlag($expected, $flags)
    {
        $dumper = new CliDumper(null, null, $flags);
        $dumper->setColors(false);
        $cloner = new VarCloner();

        $var = array(
            'array' => array('a', 'b'),
            'string' => 'hello',
            'multiline string' => "this\nis\na\multiline\nstring",
        );

        $dump = $dumper->dump($cloner->cloneVar($var), true);

        $this->assertSame($expected, $dump);
    }

    public function testDumpWithCommaFlagsAndExceptionCodeExcerpt()
    {
        $dumper = new CliDumper(null, null, CliDumper::DUMP_TRAILING_COMMA);
        $dumper->setColors(false);
        $cloner = new VarCloner();

        $ex = new \RuntimeException('foo');

        $dump = $dumper->dump($cloner->cloneVar($ex)->withRefHandles(false), true);

        $this->assertStringMatchesFormat(<<<'EOTXT'
RuntimeException {
  #message: "foo"
  #code: 0
  #file: "%ACliDumperTest.php"
  #line: %d
  trace: {
    %ACliDumperTest.php:%d {
      › 
      › $ex = new \RuntimeException('foo');
      › 
    }
    %A
  }
}

EOTXT
            , $dump);
    }

    public function provideDumpWithCommaFlagTests()
    {
        $expected = <<<'EOTXT'
array:3 [
  "array" => array:2 [
    0 => "a",
    1 => "b"
  ],
  "string" => "hello",
  "multiline string" => """
    this\n
    is\n
    a\multiline\n
    string
    """
]

EOTXT;

        yield array($expected, CliDumper::DUMP_COMMA_SEPARATOR);

        $expected = <<<'EOTXT'
array:3 [
  "array" => array:2 [
    0 => "a",
    1 => "b",
  ],
  "string" => "hello",
  "multiline string" => """
    this\n
    is\n
    a\multiline\n
    string
    """,
]

EOTXT;

        yield array($expected, CliDumper::DUMP_TRAILING_COMMA);
    }

    /**
     * @requires extension xml
     */
    public function testXmlResource()
    {
        $var = xml_parser_create();

        $this->assertDumpMatchesFormat(
            <<<'EOTXT'
xml resource {
  current_byte_index: %i
  current_column_number: %i
  current_line_number: 1
  error_code: XML_ERROR_NONE
}
EOTXT
            ,
            $var
        );
    }

    public function testJsonCast()
    {
        $var = (array) json_decode('{"0":{},"1":null}');
        foreach ($var as &$v) {
        }
        $var[] = &$v;
        $var[''] = 2;

        if (\PHP_VERSION_ID >= 70200) {
            $this->assertDumpMatchesFormat(
                <<<'EOTXT'
array:4 [
  0 => {}
  1 => &1 null
  2 => &1 null
  "" => 2
]
EOTXT
                ,
                $var
            );
        } else {
            $this->assertDumpMatchesFormat(
                <<<'EOTXT'
array:4 [
  "0" => {}
  "1" => &1 null
  0 => &1 null
  "" => 2
]
EOTXT
                ,
                $var
            );
        }
    }

    public function testObjectCast()
    {
        $var = (object) array(1 => 1);
        $var->{1} = 2;

        if (\PHP_VERSION_ID >= 70200) {
            $this->assertDumpMatchesFormat(
                <<<'EOTXT'
{
  +"1": 2
}
EOTXT
                ,
                $var
            );
        } else {
            $this->assertDumpMatchesFormat(
                <<<'EOTXT'
{
  +1: 1
  +"1": 2
}
EOTXT
                ,
                $var
            );
        }
    }

    public function testClosedResource()
    {
        $var = fopen(__FILE__, 'r');
        fclose($var);

        $dumper = new CliDumper('php://output');
        $dumper->setColors(false);
        $cloner = new VarCloner();
        $data = $cloner->cloneVar($var);

        ob_start();
        $dumper->dump($data);
        $out = ob_get_clean();
        $res = (int) $var;

        $this->assertStringMatchesFormat(
            <<<EOTXT
Closed resource @{$res}

EOTXT
            ,
            $out
        );
    }

    public function testFlags()
    {
        putenv('DUMP_LIGHT_ARRAY=1');
        putenv('DUMP_STRING_LENGTH=1');

        $var = array(
            range(1, 3),
            array('foo', 2 => 'bar'),
        );

        $this->assertDumpEquals(
            <<<EOTXT
[
  [
    1
    2
    3
  ]
  [
    0 => (3) "foo"
    2 => (3) "bar"
  ]
]
EOTXT
            ,
            $var
        );

        putenv('DUMP_LIGHT_ARRAY=');
        putenv('DUMP_STRING_LENGTH=');
    }

    /**
     * @requires function Twig\Template::getSourceContext
     */
    public function testThrowingCaster()
    {
        $out = fopen('php://memory', 'r+b');

        require_once __DIR__.'/../Fixtures/Twig.php';
        $twig = new \__TwigTemplate_VarDumperFixture_u75a09(new Environment(new FilesystemLoader()));

        $dumper = new CliDumper();
        $dumper->setColors(false);
        $cloner = new VarCloner();
        $cloner->addCasters(array(
            ':stream' => function ($res, $a) {
                unset($a['wrapper_data']);

                return $a;
            },
        ));
        $cloner->addCasters(array(
            ':stream' => eval('return function () use ($twig) {
                try {
                    $twig->render(array());
                } catch (\Twig\Error\RuntimeError $e) {
                    throw $e->getPrevious();
                }
            };'),
        ));
        $ref = (int) $out;

        $data = $cloner->cloneVar($out);
        $dumper->dump($data, $out);
        $out = stream_get_contents($out, -1, 0);

        $this->assertStringMatchesFormat(
            <<<EOTXT
stream resource {@{$ref}
  ⚠: Symfony\Component\VarDumper\Exception\ThrowingCasterException {#%d
    #message: "Unexpected Exception thrown from a caster: Foobar"
    trace: {
      %sTwig.php:2 {
        › foo bar
        ›   twig source
        › 
      }
      %s%eTemplate.php:%d { …}
      %s%eTemplate.php:%d { …}
      %s%eTemplate.php:%d { …}
      %s%eTests%eDumper%eCliDumperTest.php:%d { …}
%A  }
  }
%Awrapper_type: "PHP"
  stream_type: "MEMORY"
  mode: "%s+b"
  unread_bytes: 0
  seekable: true
  uri: "php://memory"
%Aoptions: []
}

EOTXT
            ,
            $out
        );
    }

    public function testRefsInProperties()
    {
        $var = (object) array('foo' => 'foo');
        $var->bar = &$var->foo;

        $dumper = new CliDumper();
        $dumper->setColors(false);
        $cloner = new VarCloner();

        $data = $cloner->cloneVar($var);
        $out = $dumper->dump($data, true);

        $this->assertStringMatchesFormat(
            <<<EOTXT
{#%d
  +"foo": &1 "foo"
  +"bar": &1 "foo"
}

EOTXT
            ,
            $out
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSpecialVars56()
    {
        $var = $this->getSpecialVars();

        $this->assertDumpEquals(
            <<<'EOTXT'
array:3 [
  0 => array:1 [
    0 => &1 array:1 [
      0 => &1 array:1 [&1]
    ]
  ]
  1 => array:1 [
    "GLOBALS" => &2 array:1 [
      "GLOBALS" => &2 array:1 [&2]
    ]
  ]
  2 => &2 array:1 [&2]
]
EOTXT
            ,
            $var
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGlobals()
    {
        $var = $this->getSpecialVars();
        unset($var[0]);
        $out = '';

        $dumper = new CliDumper(function ($line, $depth) use (&$out) {
            if ($depth >= 0) {
                $out .= str_repeat('  ', $depth).$line."\n";
            }
        });
        $dumper->setColors(false);
        $cloner = new VarCloner();

        $data = $cloner->cloneVar($var);
        $dumper->dump($data);

        $this->assertSame(
            <<<'EOTXT'
array:2 [
  1 => array:1 [
    "GLOBALS" => &1 array:1 [
      "GLOBALS" => &1 array:1 [&1]
    ]
  ]
  2 => &1 array:1 [&1]
]

EOTXT
            ,
            $out
        );
    }

    public function testIncompleteClass()
    {
        $unserializeCallbackHandler = ini_set('unserialize_callback_func', null);
        $var = unserialize('O:8:"Foo\Buzz":0:{}');
        ini_set('unserialize_callback_func', $unserializeCallbackHandler);

        $this->assertDumpMatchesFormat(
            <<<EOTXT
__PHP_Incomplete_Class(Foo\Buzz) {}
EOTXT
            ,
            $var
        );
    }

    private function getSpecialVars()
    {
        foreach (array_keys($GLOBALS) as $var) {
            if ('GLOBALS' !== $var) {
                unset($GLOBALS[$var]);
            }
        }

        $var = function &() {
            $var = array();
            $var[] = &$var;

            return $var;
        };

        return array($var(), $GLOBALS, &$GLOBALS);
    }
}
