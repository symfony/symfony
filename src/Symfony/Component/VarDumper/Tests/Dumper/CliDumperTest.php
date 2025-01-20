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
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\VarDumper\Caster\ClassStub;
use Symfony\Component\VarDumper\Caster\CutStub;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\AbstractDumper;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;
use Symfony\Component\VarDumper\Tests\Fixtures\VirtualProperty;
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
        $cloner->addCasters([
            ':stream' => function ($res, $a) {
                unset($a['uri'], $a['wrapper_data']);

                return $a;
            },
            'Symfony\Component\VarDumper\Tests\Fixture\DumbFoo' => function ($foo, $a) {
                $a['foo'] = new CutStub($a['foo']);

                return $a;
            },
        ]);
        $data = $cloner->cloneVar($var);

        ob_start();
        $dumper->dump($data);
        $out = ob_get_clean();
        $out = preg_replace('/[ \t]+$/m', '', $out);
        $intMax = \PHP_INT_MAX;
        $res = (int) $var['res'];

        $this->assertStringMatchesFormat(
            <<<EOTXT
array:25 [
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
  7 => b"""
    é\\x01test\\t\\n
    ing
    """
  "bo\\u{FEFF}m" => "te\\u{FEFF}st"
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
    +foo: ""…3
    +"bar": "bar"
  }
  "closure" => Closure(\$a, ?PDO &\$b = null) {#%d
    class: "Symfony\Component\VarDumper\Tests\Dumper\CliDumperTest"
    this: Symfony\Component\VarDumper\Tests\Dumper\CliDumperTest {#%d …}
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

        $var = [
            'array' => ['a', 'b'],
            'string' => 'hello',
            'multiline string' => "this\nis\na\multiline\nstring",
        ];

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
      Symfony\Component\VarDumper\Tests\Dumper\CliDumperTest->testDumpWithCommaFlagsAndExceptionCodeExcerpt()
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

    public static function provideDumpWithCommaFlagTests()
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

        yield [$expected, CliDumper::DUMP_COMMA_SEPARATOR];

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

        yield [$expected, CliDumper::DUMP_TRAILING_COMMA];
    }

    public function testJsonCast()
    {
        $var = (array) json_decode('{"0":{},"1":null}');
        foreach ($var as &$v) {
        }
        $var[] = &$v;
        $var[''] = 2;

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
    }

    public function testObjectCast()
    {
        $var = (object) [1 => 1];
        $var->{1} = 2;

        $this->assertDumpMatchesFormat(
            <<<'EOTXT'
{
  +"1": 2
}
EOTXT
            ,
            $var
        );
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

        $var = [
            range(1, 3),
            ['foo', 2 => 'bar'],
        ];

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
     * @requires PHP 8.4
     */
    public function testVirtualProperties()
    {
        $this->assertDumpEquals(<<<EODUMP
            Symfony\Component\VarDumper\Tests\Fixtures\VirtualProperty {
              +firstName: "John"
              +lastName: "Doe"
              +fullName: ~ string
              -noType: ~
            }
            EODUMP, new VirtualProperty());
    }

    public function testThrowingCaster()
    {
        $out = fopen('php://memory', 'r+');

        require_once __DIR__.'/../Fixtures/Twig.php';
        $twig = new \__TwigTemplate_VarDumperFixture_u75a09(new Environment(new FilesystemLoader()));

        $dumper = new CliDumper();
        $dumper->setColors(false);
        $cloner = new VarCloner();
        $cloner->addCasters([
            ':stream' => function ($res, $a) {
                unset($a['wrapper_data']);

                return $a;
            },
        ]);
        $cloner->addCasters([
            ':stream' => eval('return function () use ($twig) {
                try {
                    $twig->render([]);
                } catch (\Twig\Error\RuntimeError $e) {
                    throw $e->getPrevious();
                }
            };'),
        ]);
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
        __TwigTemplate_VarDumperFixture_u75a09->doDisplay(array \$context, array \$blocks = []): array
        › foo bar
        ›   twig source
        › 
      }
      %A%eTemplate.php:%d { …}
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
        $var = (object) ['foo' => 'foo'];
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

    public static function provideDumpArrayWithColor()
    {
        yield [
            ['foo' => 'bar'],
            0,
            <<<EOTXT
\e[0;38;5;208m\e[38;5;38marray:1\e[0;38;5;208m [\e[m
  \e[0;38;5;208m"\e[38;5;113mfoo\e[0;38;5;208m" => "\e[1;38;5;113mbar\e[0;38;5;208m"\e[m
\e[0;38;5;208m]\e[m

EOTXT,
        ];

        yield [[], AbstractDumper::DUMP_LIGHT_ARRAY, "\e[0;38;5;208m[]\e[m\n"];

        yield [
            ['foo' => 'bar'],
            AbstractDumper::DUMP_LIGHT_ARRAY,
            <<<EOTXT
\e[0;38;5;208m[\e[m
  \e[0;38;5;208m"\e[38;5;113mfoo\e[0;38;5;208m" => "\e[1;38;5;113mbar\e[0;38;5;208m"\e[m
\e[0;38;5;208m]\e[m

EOTXT,
        ];

        yield [[], 0, "\e[0;38;5;208m[]\e[m\n"];
    }

    /**
     * @dataProvider provideDumpArrayWithColor
     */
    public function testDumpArrayWithColor($value, $flags, $expectedOut)
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows console does not support coloration');
        }

        $out = '';
        $dumper = new CliDumper(function ($line, $depth) use (&$out) {
            if ($depth >= 0) {
                $out .= str_repeat('  ', $depth).$line."\n";
            }
        }, null, $flags);
        $dumper->setColors(true);
        $cloner = new VarCloner();
        $dumper->dump($cloner->cloneVar($value));

        $this->assertSame($expectedOut, $out);
    }

    public function testCollapse()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test cannot be run on Windows.');
        }

        $stub = new Stub();
        $stub->type = Stub::TYPE_OBJECT;
        $stub->class = 'stdClass';
        $stub->position = 1;

        $data = new Data([
            [
                $stub,
            ],
            [
                "\0~collapse=1\0foo" => 123,
                "\0+\0bar" => [1 => 2],
            ],
            [
                'bar' => 123,
            ],
        ]);

        $dumper = new CliDumper();
        $dump = $dumper->dump($data, true);

        $this->assertSame(
            <<<'EOTXT'
{
  foo: 123
  +"bar": array:1 [
    "bar" => 123
  ]
}

EOTXT
            ,
            $dump
        );
    }

    public function testFileLinkFormat()
    {
        if (!class_exists(FileLinkFormatter::class)) {
            $this->markTestSkipped(\sprintf('Class "%s" is required to run this test.', FileLinkFormatter::class));
        }

        $data = new Data([
            [
                new ClassStub(self::class),
            ],
        ]);

        $ide = $_ENV['SYMFONY_IDE'] ?? null;
        $_ENV['SYMFONY_IDE'] = 'vscode';

        try {
            $dumper = new CliDumper();
            $dumper->setColors(true);
            $dump = $dumper->dump($data, true);

            $this->assertStringMatchesFormat('%svscode:%sCliDumperTest%s', $dump);
        } finally {
            if (null === $ide) {
                unset($_ENV['SYMFONY_IDE']);
            } else {
                $_ENV['SYMFONY_IDE'] = $ide;
            }
        }
    }
}
