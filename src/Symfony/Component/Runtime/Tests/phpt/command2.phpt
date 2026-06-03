--TEST--
Test Command
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/command2.php';

?>
--EXPECTF--
Hello World
OK Command foo_bar
