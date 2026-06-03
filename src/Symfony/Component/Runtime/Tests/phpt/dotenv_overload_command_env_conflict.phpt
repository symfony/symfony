--TEST--
Test that a command --env option conflicts with the different one defined in .env when the dotenv_overload option is true
--INI--
display_errors=1
--FILE--
<?php

$_SERVER['argv'] = [
    'my_app',
    '--env=ccc',
];
$_SERVER['argc'] = 2;
require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/dotenv_overload_command_env_conflict.php';

?>
--EXPECTF--
Fatal error: Uncaught LogicException: Cannot use "--env" or "-e" when the ".env" file defines "ENV_MODE" and the "dotenv_overload" runtime option is true.%a
