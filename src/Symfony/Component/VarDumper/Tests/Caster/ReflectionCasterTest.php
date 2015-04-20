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

use Symfony\Component\VarDumper\Test\VarDumperTestCase;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ReflectionCasterTest extends VarDumperTestCase
{
    public function testReflectionCaster()
    {
        $var = new \ReflectionClass('ReflectionClass');

        $this->assertDumpMatchesFormat(
            <<<'EOTXT'
ReflectionClass {
  +name: "ReflectionClass"
  implements: array:1 [
    0 => "Reflector"
  ]
  constants: array:3 [
    "IS_IMPLICIT_ABSTRACT" => 16
    "IS_EXPLICIT_ABSTRACT" => 32
    "IS_FINAL" => 64
  ]
  properties: array:1 [
    "name" => ReflectionProperty {
      +name: "name"
      +class: "ReflectionClass"
      modifiers: "public"
      extra: null
    }
  ]
  methods: array:%d [
%A
    "export" => ReflectionMethod {
      +name: "export"
      +class: "ReflectionClass"
      parameters: array:2 [
        "$argument" => ReflectionParameter {
          +name: "argument"
          position: 0
        }
        "$return" => ReflectionParameter {
          +name: "return"
          position: 1
        }
      ]
      modifiers: "public static"
    }
%A
}
EOTXT
            , $var
        );
    }
}
