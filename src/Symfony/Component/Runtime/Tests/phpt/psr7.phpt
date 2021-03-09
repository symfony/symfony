--TEST--
Test PSR-7 Request/Response
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/psr7.php';

?>
--EXPECTF--
Hello PSR-7
