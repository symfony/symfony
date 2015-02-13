<?php

namespace Symfony\Component\VarDumper\Tests\Fixture;

if (!class_exists('Symfony\Component\VarDumper\Tests\Fixture\DumbFoo')) {
    class DumbFoo
    {
        public $foo = 'foo';
    }
}

$foo = new DumbFoo();
$foo->bar = 'bar';

$g = fopen(__FILE__, 'r');
$h = fopen(__FILE__, 'r');
fclose($h);

$var = array(
    'number' => 1, null,
    'const' => 1.1, true, false, NAN, INF, -INF, PHP_INT_MAX,
    'str' => "déjà", "\xE9\x00",
    '[]' => array(),
    'res' => $g,
    $h,
    'obj' => $foo,
    'closure' => function ($a, \PDO &$b = null) {},
    'line' => __LINE__ - 1,
    'nobj' => array((object) array()),
);

$r = array();
$r[] =& $r;

$var['recurs'] =& $r;
$var[] =& $var[0];
$var['sobj'] = $var['obj'];
$var['snobj'] =& $var['nobj'][0];
$var['snobj2'] = $var['nobj'][0];
$var['file'] = __FILE__;
$var["bin-key-\xE9"] = "";

unset($g, $h, $r);
