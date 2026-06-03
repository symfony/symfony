--TEST--
Test Request/Response
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/generic-request.php';

?>
--EXPECTF--
OK request runtime
OK Request foo_bar
OK response runtime
