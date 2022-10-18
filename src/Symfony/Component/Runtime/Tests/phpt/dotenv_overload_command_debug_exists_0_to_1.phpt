--TEST--
Test Dotenv overload with a command when debug=0 exists and debug=1 in .env and the --no-debug option is not used
--INI--
display_errors=1
--FILE--
<?php

$_SERVER['argv'] = [
    'my_app',
];
$_SERVER['argc'] = 1;
require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/dotenv_overload_command_debug_exists_0_to_1.php';

?>
--EXPECTF--
1
