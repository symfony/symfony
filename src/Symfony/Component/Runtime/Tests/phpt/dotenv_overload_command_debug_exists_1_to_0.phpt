--TEST--
Test Dotenv overload with a command when debug=1 exists and debug=0 in .env and the --no-debug option is not used
--INI--
display_errors=1
--FILE--
<?php

$_SERVER['argv'] = [
    'my_app',
];
$_SERVER['argc'] = 1;
require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/dotenv_overload_command_debug_exists_1_to_0.php';

?>
--EXPECTF--
0
