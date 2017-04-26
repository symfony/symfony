--TEST--
Test symfony_zval_info API
--SKIPIF--
<?php if (!extension_loaded("symfony_debug")) print "skip"; ?>
--FILE--
<?php 
$int = 42;
$float = 42.42;
$str = "foobar";
$object = new StdClass;
$array = array($int, $str);
$resource = tmpfile();
$null = null;
$bool = true;

$anotherint = 42;
$refcount2 = &$anotherint;

var_dump(symfony_zval_info($int));
var_dump(symfony_zval_info($float));
var_dump(symfony_zval_info($str));
var_dump(symfony_zval_info($object));
var_dump(symfony_zval_info($array));
var_dump(symfony_zval_info($resource));
var_dump(symfony_zval_info($null));
var_dump(symfony_zval_info($bool));

var_dump(symfony_zval_info($refcount2));
?>
--EXPECTF--
array(3) {
  ["type"]=>
  string(7) "integer"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(1)
}
array(3) {
  ["type"]=>
  string(6) "double"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(1)
}
array(4) {
  ["type"]=>
  string(6) "string"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(1)
  ["strlen"]=>
  int(6)
}
array(6) {
  ["type"]=>
  string(6) "object"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(1)
  ["object_class"]=>
  string(8) "stdClass"
  ["object_refcount"]=>
  int(1)
  ["object_hash"]=>
  string(32) "%s"
}
array(4) {
  ["type"]=>
  string(5) "array"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(1)
  ["array_count"]=>
  int(2)
}
array(6) {
  ["type"]=>
  string(8) "resource"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(1)
  ["resource_id"]=>
  int(4)
  ["resource_type"]=>
  string(6) "stream"
  ["resource_refcount"]=>
  int(1)
}
array(3) {
  ["type"]=>
  string(4) "NULL"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(1)
}
array(3) {
  ["type"]=>
  string(7) "boolean"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(1)
}
array(3) {
  ["type"]=>
  string(7) "integer"
  ["zval_hash"]=>
  string(16) "%s"
  ["zval_refcount"]=>
  int(2)
}