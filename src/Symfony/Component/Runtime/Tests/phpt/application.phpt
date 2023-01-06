--TEST--
Test Application
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/application.php';

?>
--EXPECTF--
OK Application foo_bar
