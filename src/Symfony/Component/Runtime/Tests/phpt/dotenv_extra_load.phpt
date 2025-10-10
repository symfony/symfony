--TEST--
Test Dotenv extra paths load
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/dotenv_extra_load.php';

?>
--EXPECTF--
OK Request ccc
