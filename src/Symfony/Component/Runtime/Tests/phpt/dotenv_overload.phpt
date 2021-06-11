--TEST--
Test Dotenv overload
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/dotenv_overload.php';

?>
--EXPECTF--
OK Request foo_bar
