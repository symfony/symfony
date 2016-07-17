<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests;

use Symfony\Component\VarDumper\Cloner\VarCloner;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class VarClonerTest extends \PHPUnit_Framework_TestCase
{
    public function testMaxIntBoundary()
    {
        $data = array(PHP_INT_MAX => 123);

        $cloner = new VarCloner();
        $clone = $cloner->cloneVar($data);

        $expected = <<<EOTXT
Symfony\Component\VarDumper\Cloner\Data Object
(
    [data:Symfony\Component\VarDumper\Cloner\Data:private] => Array
        (
            [0] => Array
                (
                    [0] => Symfony\Component\VarDumper\Cloner\Stub Object
                        (
                            [type] => array
                            [class] => assoc
                            [value] => 1
                            [cut] => 0
                            [handle] => 0
                            [refCount] => 0
                            [position] => 1
                        )

                )

            [1] => Array
                (
                    [%s] => 123
                )

        )

    [maxDepth:Symfony\Component\VarDumper\Cloner\Data:private] => 20
    [maxItemsPerDepth:Symfony\Component\VarDumper\Cloner\Data:private] => -1
    [useRefHandles:Symfony\Component\VarDumper\Cloner\Data:private] => -1
)

EOTXT;
        $this->assertSame(sprintf($expected, PHP_INT_MAX), print_r($clone, true));
    }

    public function testClone()
    {
        $json = json_decode('{"1":{"var":"val"},"2":{"var":"val"}}');

        $cloner = new VarCloner();
        $clone = $cloner->cloneVar($json);

        $expected = <<<EOTXT
Symfony\Component\VarDumper\Cloner\Data Object
(
    [data:Symfony\Component\VarDumper\Cloner\Data:private] => Array
        (
            [0] => Array
                (
                    [0] => Symfony\Component\VarDumper\Cloner\Stub Object
                        (
                            [type] => object
                            [class] => stdClass
                            [value] => 
                            [cut] => 0
                            [handle] => %i
                            [refCount] => 0
                            [position] => 1
                        )

                )

            [1] => Array
                (
                    [\000+\0001] => Symfony\Component\VarDumper\Cloner\Stub Object
                        (
                            [type] => object
                            [class] => stdClass
                            [value] => 
                            [cut] => 0
                            [handle] => %i
                            [refCount] => 0
                            [position] => 2
                        )

                    [\000+\0002] => Symfony\Component\VarDumper\Cloner\Stub Object
                        (
                            [type] => object
                            [class] => stdClass
                            [value] => 
                            [cut] => 0
                            [handle] => %i
                            [refCount] => 0
                            [position] => 3
                        )

                )

            [2] => Array
                (
                    [\000+\000var] => val
                )

            [3] => Array
                (
                    [\000+\000var] => val
                )

        )

    [maxDepth:Symfony\Component\VarDumper\Cloner\Data:private] => 20
    [maxItemsPerDepth:Symfony\Component\VarDumper\Cloner\Data:private] => -1
    [useRefHandles:Symfony\Component\VarDumper\Cloner\Data:private] => -1
)

EOTXT;
        $this->assertStringMatchesFormat($expected, print_r($clone, true));
    }

    public function testJsonCast()
    {
        $data = (array) json_decode('{"1":{}}');

        $cloner = new VarCloner();
        $clone = $cloner->cloneVar($data);

        $expected = <<<'EOTXT'
object(Symfony\Component\VarDumper\Cloner\Data)#%i (4) {
  ["data":"Symfony\Component\VarDumper\Cloner\Data":private]=>
  array(2) {
    [0]=>
    array(1) {
      [0]=>
      object(Symfony\Component\VarDumper\Cloner\Stub)#%i (7) {
        ["type"]=>
        string(5) "array"
        ["class"]=>
        string(5) "assoc"
        ["value"]=>
        int(1)
        ["cut"]=>
        int(0)
        ["handle"]=>
        int(0)
        ["refCount"]=>
        int(0)
        ["position"]=>
        int(1)
      }
    }
    [1]=>
    array(1) {
      ["1"]=>
      object(Symfony\Component\VarDumper\Cloner\Stub)#%i (7) {
        ["type"]=>
        string(6) "object"
        ["class"]=>
        string(8) "stdClass"
        ["value"]=>
        NULL
        ["cut"]=>
        int(0)
        ["handle"]=>
        int(%i)
        ["refCount"]=>
        int(0)
        ["position"]=>
        int(0)
      }
    }
  }
  ["maxDepth":"Symfony\Component\VarDumper\Cloner\Data":private]=>
  int(20)
  ["maxItemsPerDepth":"Symfony\Component\VarDumper\Cloner\Data":private]=>
  int(-1)
  ["useRefHandles":"Symfony\Component\VarDumper\Cloner\Data":private]=>
  int(-1)
}

EOTXT;
        ob_start();
        var_dump($clone);
        $this->assertStringMatchesFormat($expected, ob_get_clean());
    }

    public function testCaster()
    {
        $cloner = new VarCloner(array(
            '*' => function ($obj, $array) {
                return array('foo' => 123);
            },
            __CLASS__ => function ($obj, $array) {
                ++$array['foo'];

                return $array;
            },
        ));
        $clone = $cloner->cloneVar($this);

        $expected = <<<EOTXT
Symfony\Component\VarDumper\Cloner\Data Object
(
    [data:Symfony\Component\VarDumper\Cloner\Data:private] => Array
        (
            [0] => Array
                (
                    [0] => Symfony\Component\VarDumper\Cloner\Stub Object
                        (
                            [type] => object
                            [class] => %s
                            [value] => 
                            [cut] => 0
                            [handle] => %i
                            [refCount] => 0
                            [position] => 1
                        )

                )

            [1] => Array
                (
                    [foo] => 124
                )

        )

    [maxDepth:Symfony\Component\VarDumper\Cloner\Data:private] => 20
    [maxItemsPerDepth:Symfony\Component\VarDumper\Cloner\Data:private] => -1
    [useRefHandles:Symfony\Component\VarDumper\Cloner\Data:private] => -1
)

EOTXT;
        $this->assertStringMatchesFormat($expected, print_r($clone, true));
    }
}
