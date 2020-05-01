--TEST--
Test "list" Command
--INI--
display_errors=1
--FILE--
<?php

$argv = $_SERVER['argv'] = [
    'my_app',
    'list',
    '--env=prod',
];
$argc = $_SERVER['argc'] = count($argv);

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/command_list.php';

?>
--EXPECTF--
Hello console 1.2.3

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -e, --env=ENV         The Environment name. [default: "prod"]
      --no-debug        Switches off debug mode.
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  help        Displays help for a command
  list        Lists commands
  my_command  Hello description
