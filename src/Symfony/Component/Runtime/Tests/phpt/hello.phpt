--TEST--
Test Request/Response
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/hello.php';

?>
--EXPECTF--
Hello World foo_bar
