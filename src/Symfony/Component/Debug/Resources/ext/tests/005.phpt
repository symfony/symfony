--TEST--
Test symfony_debug_get_error_handler() & symfony_debug_get_error_handlers()
--SKIPIF--
<?php if (!extension_loaded("symfony_debug")) print "skip"; ?>
--FILE--
<?php 

function my_eh() { }

set_error_handler(function () { });
set_error_handler('my_eh');

var_dump(symfony_debug_get_error_handler());
var_dump(symfony_debug_get_error_handlers());
?>
--EXPECTF--
string(5) "my_eh"
array(2) {
  [0]=>
  object(Closure)#1 (0) {
  }
  [1]=>
  string(5) "my_eh"
}