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
                            [handle] => %d
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
                            [handle] => %d
                            [refCount] => 0
                            [position] => 2
                        )

                    [\000+\0002] => Symfony\Component\VarDumper\Cloner\Stub Object
                        (
                            [type] => object
                            [class] => stdClass
                            [value] => 
                            [cut] => 0
                            [handle] => %d
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
}
