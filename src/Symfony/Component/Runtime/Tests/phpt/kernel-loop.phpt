--TEST--
Test HttpKernelInterface
--INI--
display_errors=1
--FILE--
<?php

require __DIR__.'/kernel-loop.php';

?>
--EXPECTF--
OK Kernel foo_bar
OK Kernel foo_bar
0
