--TEST--
Test Request/Response
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/request.php';

?>
--EXPECTF--
OK Request foo_bar
