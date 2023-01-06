--TEST--
Test that a command --no-debug option has a higher priority than the debug value defined in .env when the dotenv_overload option is true
--INI--
display_errors=1
--FILE--
<?php

$_SERVER['argv'] = [
    'my_app',
    '--no-debug'
];
$_SERVER['argc'] = 2;
require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/dotenv_overload_command_no_debug.php';

?>
--EXPECTF--
0
