--TEST--
Test Options
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/runtime-options.php';

?>
--EXPECTF--
Env mode foo, debug mode 0
