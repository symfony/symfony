<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;
use Symfony\Component\VarDumper\Tests\Fixtures\GeneratorDemo;
use Symfony\Component\VarDumper\Tests\Fixtures\LotsOfAttributes;
use Symfony\Component\VarDumper\Tests\Fixtures\NotLoadableClass;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ReflectionCasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testReflectionCaster()
    {
        $var = new \ReflectionClass('ReflectionClass');

        $this->assertDumpMatchesFormat(
            <<<'EOTXT'
ReflectionClass {
  +name: "ReflectionClass"
%Aimplements: array:%d [
    0 => "Reflector"
%A]
  constants: array:3 [
    0 => ReflectionClassConstant {
      +name: "IS_IMPLICIT_ABSTRACT"
      +class: "ReflectionClass"
      modifiers: "public"
      value: 16
    }
    1 => ReflectionClassConstant {
      +name: "IS_EXPLICIT_ABSTRACT"
      +class: "ReflectionClass"
      modifiers: "public"
      value: %d
    }
    2 => ReflectionClassConstant {
      +name: "IS_FINAL"
      +class: "ReflectionClass"
      modifiers: "public"
      value: %d
    }
  ]
  properties: array:%d [
    "name" => ReflectionProperty {
%A    +name: "name"
      +class: "ReflectionClass"
%A    modifiers: "public"
    }
%A]
  methods: array:%d [
%A
    "__construct" => ReflectionMethod {
      +name: "__construct"
      +class: "ReflectionClass"
%A    parameters: {
        $%s: ReflectionParameter {
%A         position: 0
%A
}
EOTXT
            , $var
        );
    }

    public function testClosureCaster()
    {
        $a = $b = 123;
        $var = function ($x) use ($a, &$b) {};

        $this->assertDumpMatchesFormat(
            <<<'EOTXT'
Closure($x) {
%Ause: {
    $a: 123
    $b: & 123
  }
  file: "%sReflectionCasterTest.php"
  line: "84 to 84"
}
EOTXT
            , $var
        );
    }

    public function testFromCallableClosureCaster()
    {
        $var = [
            (new \ReflectionMethod($this, __FUNCTION__))->getClosure($this),
            (new \ReflectionMethod(__CLASS__, 'stub'))->getClosure(),
        ];

        $this->assertDumpMatchesFormat(
            <<<EOTXT
array:2 [
  0 => Symfony\Component\VarDumper\Tests\Caster\ReflectionCasterTest::testFromCallableClosureCaster() {
    this: Symfony\Component\VarDumper\Tests\Caster\ReflectionCasterTest { …}
    file: "%sReflectionCasterTest.php"
    line: "%d to %d"
  }
  1 => Symfony\Component\VarDumper\Tests\Caster\ReflectionCasterTest::stub(): void {
    returnType: "void"
    file: "%sReflectionCasterTest.php"
    line: "%d to %d"
  }
]
EOTXT
            , $var
        );
    }

    public function testClosureCasterExcludingVerbosity()
    {
        $var = function &($a = 5) {};

        $this->assertDumpEquals('Closure&($a = 5) { …5}', $var, Caster::EXCLUDE_VERBOSE);
    }

    public function testReflectionParameter()
    {
        $var = new \ReflectionParameter(reflectionParameterFixture::class, 0);

        $this->assertDumpMatchesFormat(
            <<<'EOTXT'
ReflectionParameter {
  +name: "arg1"
  position: 0
  typeHint: "Symfony\Component\VarDumper\Tests\Fixtures\NotLoadableClass"
  default: null
}
EOTXT
            , $var
        );
    }

    public function testReflectionParameterScalar()
    {
        $f = eval('return function (int $a) {};');
        $var = new \ReflectionParameter($f, 0);

        $this->assertDumpMatchesFormat(
            <<<'EOTXT'
ReflectionParameter {
  +name: "a"
  position: 0
  typeHint: "int"
}
EOTXT
            , $var
        );
    }

    /**
     * @requires PHP 8
     */
    public function testReflectionParameterMixed()
    {
        $f = eval('return function (mixed $a) {};');
        $var = new \ReflectionParameter($f, 0);

        $this->assertDumpMatchesFormat(
            <<<'EOTXT'
ReflectionParameter {
  +name: "a"
  position: 0
  allowsNull: true
  typeHint: "mixed"
}
EOTXT
            , $var
        );
    }

    /**
     * @requires PHP 8
     */
    public function testReflectionParameterUnion()
    {
        $f = eval('return function (int|float $a) {};');
        $var = new \ReflectionParameter($f, 0);

        $this->assertDumpMatchesFormat(
            <<<'EOTXT'
ReflectionParameter {
  +name: "a"
  position: 0
  typeHint: "int|float"
}
EOTXT
            , $var
        );
    }

    /**
     * @requires PHP 8
     */
    public function testReflectionParameterNullableUnion()
    {
        $f = eval('return function (int|float|null $a) {};');
        $var = new \ReflectionParameter($f, 0);

        $this->assertDumpMatchesFormat(
            <<<'EOTXT'
ReflectionParameter {
  +name: "a"
  position: 0
  allowsNull: true
  typeHint: "int|float|null"
}
EOTXT
            , $var
        );
    }

    public function testReturnType()
    {
        $f = eval('return function ():int {};');
        $line = __LINE__ - 1;

        $this->assertDumpMatchesFormat(
            <<<EOTXT
Closure(): int {
  returnType: "int"
  class: "Symfony\Component\VarDumper\Tests\Caster\ReflectionCasterTest"
  this: Symfony\Component\VarDumper\Tests\Caster\ReflectionCasterTest { …}
  file: "%sReflectionCasterTest.php($line) : eval()'d code"
  line: "1 to 1"
}
EOTXT
            , $f
        );
    }

    /**
     * @requires PHP 8
     */
    public function testMixedReturnType()
    {
        $f = eval('return function (): mixed {};');
        $line = __LINE__ - 1;

        $this->assertDumpMatchesFormat(
            <<<EOTXT
Closure(): mixed {
  returnType: "mixed"
  class: "Symfony\Component\VarDumper\Tests\Caster\ReflectionCasterTest"
  this: Symfony\Component\VarDumper\Tests\Caster\ReflectionCasterTest { …}
  file: "%sReflectionCasterTest.php($line) : eval()'d code"
  line: "1 to 1"
}
EOTXT
            , $f
        );
    }

    /**
     * @requires PHP 8
     */
    public function testUnionReturnType()
    {
        $f = eval('return function (): int|float {};');
        $line = __LINE__ - 1;

        $this->assertDumpMatchesFormat(
            <<<EOTXT
Closure(): int|float {
  returnType: "int|float"
  class: "Symfony\Component\VarDumper\Tests\Caster\ReflectionCasterTest"
  this: Symfony\Component\VarDumper\Tests\Caster\ReflectionCasterTest { …}
  file: "%sReflectionCasterTest.php($line) : eval()'d code"
  line: "1 to 1"
}
EOTXT
            , $f
        );
    }

    /**
     * @requires PHP 8
     */
    public function testNullableUnionReturnType()
    {
        $f = eval('return function (): int|float|null {};');
        $line = __LINE__ - 1;

        $this->assertDumpMatchesFormat(
            <<<EOTXT
Closure(): int|float|null {
  returnType: "int|float|null"
  class: "Symfony\Component\VarDumper\Tests\Caster\ReflectionCasterTest"
  this: Symfony\Component\VarDumper\Tests\Caster\ReflectionCasterTest { …}
  file: "%sReflectionCasterTest.php($line) : eval()'d code"
  line: "1 to 1"
}
EOTXT
            , $f
        );
    }

    public function testGenerator()
    {
        if (\extension_loaded('xdebug')) {
            $this->markTestSkipped('xdebug is active');
        }

        $generator = new GeneratorDemo();
        $generator = $generator->baz();

        $expectedDump = <<<'EODUMP'
Generator {
  this: Symfony\Component\VarDumper\Tests\Fixtures\GeneratorDemo { …}
  executing: {
    %sGeneratorDemo.php:14 {
      Symfony\Component\VarDumper\Tests\Fixtures\GeneratorDemo->baz()
      › {
      ›     yield from bar();
      › }
    }
  }
  closed: false
}
EODUMP;

        $this->assertDumpMatchesFormat($expectedDump, $generator);

        foreach ($generator as $v) {
            break;
        }

        $expectedDump = <<<'EODUMP'
array:2 [
  0 => ReflectionGenerator {
    this: Symfony\Component\VarDumper\Tests\Fixtures\GeneratorDemo { …}
    trace: {
      %s%eTests%eFixtures%eGeneratorDemo.php:9 {
        Symfony\Component\VarDumper\Tests\Fixtures\GeneratorDemo::foo()
        › {
        ›     yield 1;
        › }
      }
      %s%eTests%eFixtures%eGeneratorDemo.php:20 { …}
      %s%eTests%eFixtures%eGeneratorDemo.php:14 { …}
    }
    closed: false
  }
  1 => Generator {
    executing: {
      %sGeneratorDemo.php:10 {
        Symfony\Component\VarDumper\Tests\Fixtures\GeneratorDemo::foo()
        ›     yield 1;
        › }
        › 
      }
    }
    closed: false
  }
]
EODUMP;

        $r = new \ReflectionGenerator($generator);
        $this->assertDumpMatchesFormat($expectedDump, [$r, $r->getExecutingGenerator()]);

        foreach ($generator as $v) {
        }

        $expectedDump = <<<'EODUMP'
Generator {
  closed: true
}
EODUMP;
        $this->assertDumpMatchesFormat($expectedDump, $generator);
    }

    /**
     * @requires PHP 8
     */
    public function testReflectionClassWithAttribute()
    {
        $var = new \ReflectionClass(LotsOfAttributes::class);

        $this->assertDumpMatchesFormat(<<< 'EOTXT'
ReflectionClass {
  +name: "Symfony\Component\VarDumper\Tests\Fixtures\LotsOfAttributes"
%A  attributes: array:1 [
    0 => ReflectionAttribute {
      name: "Symfony\Component\VarDumper\Tests\Fixtures\MyAttribute"
      arguments: []
    }
  ]
%A
}
EOTXT
            , $var);
    }

    /**
     * @requires PHP 8
     */
    public function testReflectionMethodWithAttribute()
    {
        $var = new \ReflectionMethod(LotsOfAttributes::class, 'someMethod');

        $this->assertDumpMatchesFormat(<<< 'EOTXT'
ReflectionMethod {
  +name: "someMethod"
  +class: "Symfony\Component\VarDumper\Tests\Fixtures\LotsOfAttributes"
%A  attributes: array:1 [
    0 => ReflectionAttribute {
      name: "Symfony\Component\VarDumper\Tests\Fixtures\MyAttribute"
      arguments: array:1 [
        0 => "two"
      ]
    }
  ]
%A
}
EOTXT
            , $var);
    }

    /**
     * @requires PHP 8
     */
    public function testReflectionPropertyWithAttribute()
    {
        $var = new \ReflectionProperty(LotsOfAttributes::class, 'someProperty');

        $this->assertDumpMatchesFormat(<<< 'EOTXT'
ReflectionProperty {
  +name: "someProperty"
  +class: "Symfony\Component\VarDumper\Tests\Fixtures\LotsOfAttributes"
%A  attributes: array:1 [
    0 => ReflectionAttribute {
      name: "Symfony\Component\VarDumper\Tests\Fixtures\MyAttribute"
      arguments: array:2 [
        0 => "one"
        "extra" => "hello"
      ]
    }
  ]
}
EOTXT
            , $var);
    }

    /**
     * @requires PHP 8
     */
    public function testReflectionClassConstantWithAttribute()
    {
        $var = new \ReflectionClassConstant(LotsOfAttributes::class, 'SOME_CONSTANT');

        $this->assertDumpMatchesFormat(<<< 'EOTXT'
ReflectionClassConstant {
  +name: "SOME_CONSTANT"
  +class: "Symfony\Component\VarDumper\Tests\Fixtures\LotsOfAttributes"
  modifiers: "public"
  value: "some value"
  attributes: array:2 [
    0 => ReflectionAttribute {
      name: "Symfony\Component\VarDumper\Tests\Fixtures\RepeatableAttribute"
      arguments: array:1 [
        0 => "one"
      ]
    }
    1 => ReflectionAttribute {
      name: "Symfony\Component\VarDumper\Tests\Fixtures\RepeatableAttribute"
      arguments: array:1 [
        0 => "two"
      ]
    }
  ]
}
EOTXT
            , $var);
    }

    /**
     * @requires PHP 8
     */
    public function testReflectionParameterWithAttribute()
    {
        $var = new \ReflectionParameter([LotsOfAttributes::class, 'someMethod'], 'someParameter');

        $this->assertDumpMatchesFormat(<<< 'EOTXT'
ReflectionParameter {
  +name: "someParameter"
  position: 0
  attributes: array:1 [
    0 => ReflectionAttribute {
      name: "Symfony\Component\VarDumper\Tests\Fixtures\MyAttribute"
      arguments: array:1 [
        0 => "three"
      ]
    }
  ]
%A
}
EOTXT
            , $var);
    }

    public static function stub(): void
    {
    }
}

function reflectionParameterFixture(NotLoadableClass $arg1 = null, $arg2)
{
}
