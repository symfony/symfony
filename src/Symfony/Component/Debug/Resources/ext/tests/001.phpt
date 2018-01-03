--TEST--
Test symfony_zval_info API
--SKIPIF--
<?php if (!extension_loaded('symfony_debug')) {
    echo 'skip';
} ?>
--FILE--
<?php

$int = 42;
$float = 42.42;
$str = 'foobar';
$object = new StdClass();
$array = array('foo', 'bar');
$resource = tmpfile();
$null = null;
$bool = true;

$anotherint = 42;
$refcount2 = &$anotherint;

$var = array(
    'int' => $int,
    'float' => $float,
    'str' => $str,
    'object' => $object,
    'array' => $array,
    'resource' => $resource,
    'null' => $null,
    'bool' => $bool,
    'refcount' => &$refcount2,
);

var_dump(symfony_zval_info('int', $var));
var_dump(symfony_zval_info('float', $var));
var_dump(symfony_zval_info('str', $var));
var_dump(symfony_zval_info('object', $var));
var_dump(symfony_zval_info('array', $var));
var_dump(symfony_zval_info('resource', $var));
var_dump(symfony_zval_info('null', $var));
var_dump(symfony_zval_info('bool', $var));

var_dump(symfony_zval_info('refcount', $var));
var_dump(symfony_zval_info('not-exist', $var));
?>
--EXPECTF--
array(4) {
  ["type"]=>
  string(7) "integer"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(2)
  ["zval_isref"]=>
  bool(false)
}
array(4) {
  ["type"]=>
  string(6) "double"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(2)
  ["zval_isref"]=>
  bool(false)
}
array(5) {
  ["type"]=>
  string(6) "string"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(2)
  ["zval_isref"]=>
  bool(false)
  ["strlen"]=>
  int(6)
}
array(8) {
  ["type"]=>
  string(6) "object"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(2)
  ["zval_isref"]=>
  bool(false)
  ["object_class"]=>
  string(8) "stdClass"
  ["object_refcount"]=>
  int(1)
  ["object_hash"]=>
  string(32) "%s"
  ["object_handle"]=>
  int(%d)
}
array(5) {
  ["type"]=>
  string(5) "array"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(2)
  ["zval_isref"]=>
  bool(false)
  ["array_count"]=>
  int(2)
}
array(7) {
  ["type"]=>
  string(8) "resource"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(2)
  ["zval_isref"]=>
  bool(false)
  ["resource_handle"]=>
  int(%d)
  ["resource_type"]=>
  string(6) "stream"
  ["resource_refcount"]=>
  int(1)
}
array(4) {
  ["type"]=>
  string(4) "NULL"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(2)
  ["zval_isref"]=>
  bool(false)
}
array(4) {
  ["type"]=>
  string(7) "boolean"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(2)
  ["zval_isref"]=>
  bool(false)
}
array(4) {
  ["type"]=>
  string(7) "integer"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(3)
  ["zval_isref"]=>
  bool(true)
}
NULL
