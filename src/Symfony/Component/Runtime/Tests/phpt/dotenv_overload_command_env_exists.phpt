--TEST--
Test Dotenv overload with a command when existing env=notfoo and env=foo in .env and the --env option is not used
--INI--
display_errors=1
--FILE--
<?php

$_SERVER['argv'] = [
    'my_app',
];
$_SERVER['argc'] = 1;
require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/dotenv_overload_command_env_exists.php';

?>
--EXPECTF--
foo
