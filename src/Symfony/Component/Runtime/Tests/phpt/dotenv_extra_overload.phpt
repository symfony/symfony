--TEST--
Test Dotenv extra paths overload
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/dotenv_extra_overload.php';

?>
--EXPECTF--
OK Request foo_bar_extra
