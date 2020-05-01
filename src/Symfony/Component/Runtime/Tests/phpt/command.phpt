--TEST--
Test Command
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/command.php';

?>
--EXPECTF--
OK Command foo_bar
