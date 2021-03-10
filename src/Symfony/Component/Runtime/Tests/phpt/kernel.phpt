--TEST--
Test HttpKernelInterface
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/kernel.php';

?>
--EXPECTF--
OK Kernel foo_bar
