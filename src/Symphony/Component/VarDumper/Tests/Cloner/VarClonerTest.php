<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\VarDumper\Tests\Cloner;

use PHPUnit\Framework\TestCase;
use Symphony\Component\VarDumper\Cloner\VarCloner;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class VarClonerTest extends TestCase
{
    public function testMaxIntBoundary()
    {
        $data = array(PHP_INT_MAX => 123);

        $cloner = new VarCloner();
        $clone = $cloner->cloneVar($data);

        $expected = <<<EOTXT
Symphony\Component\VarDumper\Cloner\Data Object
(
    [data:Symphony\Component\VarDumper\Cloner\Data:private] => Array
        (
            [0] => Array
                (
                    [0] => Array
                        (
                            [1] => 1
                        )

                )

            [1] => Array
                (
                    [%s] => 123
                )

        )

    [position:Symphony\Component\VarDumper\Cloner\Data:private] => 0
    [key:Symphony\Component\VarDumper\Cloner\Data:private] => 0
    [maxDepth:Symphony\Component\VarDumper\Cloner\Data:private] => 20
    [maxItemsPerDepth:Symphony\Component\VarDumper\Cloner\Data:private] => -1
    [useRefHandles:Symphony\Component\VarDumper\Cloner\Data:private] => -1
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
Symphony\Component\VarDumper\Cloner\Data Object
(
    [data:Symphony\Component\VarDumper\Cloner\Data:private] => Array
        (
            [0] => Array
                (
                    [0] => Symphony\Component\VarDumper\Cloner\Stub Object
                        (
                            [type] => 4
                            [class] => stdClass
                            [value] => 
                            [cut] => 0
                            [handle] => %i
                            [refCount] => 0
                            [position] => 1
                            [attr] => Array
                                (
                                )

                        )

                )

            [1] => Array
                (
                    [\000+\0001] => Symphony\Component\VarDumper\Cloner\Stub Object
                        (
                            [type] => 4
                            [class] => stdClass
                            [value] => 
                            [cut] => 0
                            [handle] => %i
                            [refCount] => 0
                            [position] => 2
                            [attr] => Array
                                (
                                )

                        )

                    [\000+\0002] => Symphony\Component\VarDumper\Cloner\Stub Object
                        (
                            [type] => 4
                            [class] => stdClass
                            [value] => 
                            [cut] => 0
                            [handle] => %i
                            [refCount] => 0
                            [position] => 3
                            [attr] => Array
                                (
                                )

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

    [position:Symphony\Component\VarDumper\Cloner\Data:private] => 0
    [key:Symphony\Component\VarDumper\Cloner\Data:private] => 0
    [maxDepth:Symphony\Component\VarDumper\Cloner\Data:private] => 20
    [maxItemsPerDepth:Symphony\Component\VarDumper\Cloner\Data:private] => -1
    [useRefHandles:Symphony\Component\VarDumper\Cloner\Data:private] => -1
)

EOTXT;
        $this->assertStringMatchesFormat($expected, print_r($clone, true));
    }

    public function testLimits()
    {
        // Level 0:
        $data = array(
            // Level 1:
            array(
                // Level 2:
                array(
                    // Level 3:
                    'Level 3 Item 0',
                    'Level 3 Item 1',
                    'Level 3 Item 2',
                    'Level 3 Item 3',
                ),
                array(
                    'Level 3 Item 4',
                    'Level 3 Item 5',
                    'Level 3 Item 6',
                ),
                array(
                    'Level 3 Item 7',
                ),
            ),
            array(
                array(
                    'Level 3 Item 8',
                ),
                'Level 2 Item 0',
            ),
            array(
                'Level 2 Item 1',
            ),
            'Level 1 Item 0',
            array(
                // Test setMaxString:
                'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                'SHORT',
            ),
        );

        $cloner = new VarCloner();
        $cloner->setMinDepth(2);
        $cloner->setMaxItems(5);
        $cloner->setMaxString(20);
        $clone = $cloner->cloneVar($data);

        $expected = <<<EOTXT
Symphony\Component\VarDumper\Cloner\Data Object
(
    [data:Symphony\Component\VarDumper\Cloner\Data:private] => Array
        (
            [0] => Array
                (
                    [0] => Array
                        (
                            [2] => 1
                        )

                )

            [1] => Array
                (
                    [0] => Array
                        (
                            [2] => 2
                        )

                    [1] => Array
                        (
                            [2] => 3
                        )

                    [2] => Array
                        (
                            [2] => 4
                        )

                    [3] => Level 1 Item 0
                    [4] => Array
                        (
                            [2] => 5
                        )

                )

            [2] => Array
                (
                    [0] => Array
                        (
                            [2] => 6
                        )

                    [1] => Array
                        (
                            [0] => 2
                            [2] => 7
                        )

                    [2] => Array
                        (
                            [0] => 1
                            [2] => 0
                        )

                )

            [3] => Array
                (
                    [0] => Array
                        (
                            [0] => 1
                            [2] => 0
                        )

                    [1] => Level 2 Item 0
                )

            [4] => Array
                (
                    [0] => Level 2 Item 1
                )

            [5] => Array
                (
                    [0] => Symphony\Component\VarDumper\Cloner\Stub Object
                        (
                            [type] => 2
                            [class] => 2
                            [value] => ABCDEFGHIJKLMNOPQRST
                            [cut] => 6
                            [handle] => 0
                            [refCount] => 0
                            [position] => 0
                            [attr] => Array
                                (
                                )

                        )

                    [1] => SHORT
                )

            [6] => Array
                (
                    [0] => Level 3 Item 0
                    [1] => Level 3 Item 1
                    [2] => Level 3 Item 2
                    [3] => Level 3 Item 3
                )

            [7] => Array
                (
                    [0] => Level 3 Item 4
                )

        )

    [position:Symphony\Component\VarDumper\Cloner\Data:private] => 0
    [key:Symphony\Component\VarDumper\Cloner\Data:private] => 0
    [maxDepth:Symphony\Component\VarDumper\Cloner\Data:private] => 20
    [maxItemsPerDepth:Symphony\Component\VarDumper\Cloner\Data:private] => -1
    [useRefHandles:Symphony\Component\VarDumper\Cloner\Data:private] => -1
)

EOTXT;
        $this->assertStringMatchesFormat($expected, print_r($clone, true));
    }

    public function testJsonCast()
    {
        if (2 == ini_get('xdebug.overload_var_dump')) {
            $this->markTestSkipped('xdebug is active');
        }

        $data = (array) json_decode('{"1":{}}');

        $cloner = new VarCloner();
        $clone = $cloner->cloneVar($data);

        $expected = <<<'EOTXT'
object(Symphony\Component\VarDumper\Cloner\Data)#%i (6) {
  ["data":"Symphony\Component\VarDumper\Cloner\Data":private]=>
  array(2) {
    [0]=>
    array(1) {
      [0]=>
      array(1) {
        [1]=>
        int(1)
      }
    }
    [1]=>
    array(1) {
      ["1"]=>
      object(Symphony\Component\VarDumper\Cloner\Stub)#%i (8) {
        ["type"]=>
        int(4)
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
        ["attr"]=>
        array(0) {
        }
      }
    }
  }
  ["position":"Symphony\Component\VarDumper\Cloner\Data":private]=>
  int(0)
  ["key":"Symphony\Component\VarDumper\Cloner\Data":private]=>
  int(0)
  ["maxDepth":"Symphony\Component\VarDumper\Cloner\Data":private]=>
  int(20)
  ["maxItemsPerDepth":"Symphony\Component\VarDumper\Cloner\Data":private]=>
  int(-1)
  ["useRefHandles":"Symphony\Component\VarDumper\Cloner\Data":private]=>
  int(-1)
}

EOTXT;
        ob_start();
        var_dump($clone);
        $this->assertStringMatchesFormat(\PHP_VERSION_ID >= 70200 ? str_replace('"1"', '1', $expected) : $expected, ob_get_clean());
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
Symphony\Component\VarDumper\Cloner\Data Object
(
    [data:Symphony\Component\VarDumper\Cloner\Data:private] => Array
        (
            [0] => Array
                (
                    [0] => Symphony\Component\VarDumper\Cloner\Stub Object
                        (
                            [type] => 4
                            [class] => %s
                            [value] => 
                            [cut] => 0
                            [handle] => %i
                            [refCount] => 0
                            [position] => 1
                            [attr] => Array
                                (
                                )

                        )

                )

            [1] => Array
                (
                    [foo] => 124
                )

        )

    [position:Symphony\Component\VarDumper\Cloner\Data:private] => 0
    [key:Symphony\Component\VarDumper\Cloner\Data:private] => 0
    [maxDepth:Symphony\Component\VarDumper\Cloner\Data:private] => 20
    [maxItemsPerDepth:Symphony\Component\VarDumper\Cloner\Data:private] => -1
    [useRefHandles:Symphony\Component\VarDumper\Cloner\Data:private] => -1
)

EOTXT;
        $this->assertStringMatchesFormat($expected, print_r($clone, true));
    }
}
