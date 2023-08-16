--TEST--
Test set ENV variable APP_RUNTIME_MODE by guessing it from PHP_SAPI
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/runtime_mode_from_default.php';

?>
--EXPECTF--
From context cli, from $_ENV cli
