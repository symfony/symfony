--TEST--
Test dump() with "Accept: application/json" uses CliDumper with php://output
--FILE--
<?php
putenv('NO_COLOR=1');

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$_SERVER['HTTP_ACCEPT'] = 'application/json';
dump(1, 'foo', ['foo1', 'foo2']);
--EXPECTF--
1 1
2 "foo"
3 array:2 [
  0 => "foo1"
  1 => "foo2"
]
