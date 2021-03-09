--TEST--
Test PSR-15 Request/Response
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/psr15.php';

?>
--EXPECTF--
Hello PSR-15
