<?php

namespace Symfony\Component\VarDumper\Tests\Caster;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Caster\ClassStub;

require_once \dirname(__DIR__).'/Fixtures/SimpleClass.php';

class ClassStubTest extends TestCase
{
    /**
     * @param $identifier
     * @param $callable
     * @param $result
     *
     * @dataProvider getIdentifiers
     */
    public function testConstruct($identifier, $callable, $result)
    {
        $stub = new ClassStub($identifier, $callable);

        $this->assertEquals(serialize($result), $stub->serialize());
    }

    public function getIdentifiers()
    {
        $baseDir = \dirname(__DIR__);

        return array(
            array('\stdClass', null, array(
                '',
                0,
                0,
                1,
                '\stdClass',
                0,
                0,
                array(),
            )),
            array('\stdClass::staticMethod()', null, array(
                '',
                0,
                0,
                1,
                '\stdClass::staticMethod()',
                0,
                0,
                array(),
            )),
            array('\stdClass->classMethod()', null, array(
                '',
                0,
                0,
                1,
                '\stdClass->classMethod()',
                0,
                0,
                array(),
            )),
            array(\SimpleClass::class.'::staticMethod()', null, array(
                '',
                0,
                0,
                1,
                'SimpleClass::staticMethod()',
                0,
                0,
                array(
                    'file' => $baseDir.'/Fixtures/SimpleClass.php',
                    'line' => 3,
                ),
            )),
            array(\SimpleClass::class.'->classMethod()', null, array(
                '',
                0,
                0,
                1,
                'SimpleClass->classMethod()',
                0,
                0,
                array(
                    'file' => $baseDir.'/Fixtures/SimpleClass.php',
                    'line' => 3,
                ),
            )),
            array(\SimpleClass::class.'->'.\SimpleClass::class.'\{closure}', null, array(
                '',
                0,
                0,
                1,
                'SimpleClass->SimpleClass\{closure}',
                0,
                0,
                array(
                    'ellipsis' => 10,
                    'ellipsis-type' => 'class',
                    'ellipsis-tail' => 1,
                    'file' => $baseDir.'/Fixtures/SimpleClass.php',
                    'line' => 3,
                ),
            )),
        );
    }
}
