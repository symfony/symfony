--TEST--
Test Dotenv overload
--SKIPIF--
<?php require dirname(__DIR__, 6).'/vendor/autoload.php'; if (4 > (new \ReflectionMethod(\Symfony\Component\Dotenv\Dotenv::class, 'bootEnv'))->getNumberOfParameters()) die('Skip because Dotenv version is too low');
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/dotenv_overload.php';

?>
--EXPECTF--
OK Request foo_bar
