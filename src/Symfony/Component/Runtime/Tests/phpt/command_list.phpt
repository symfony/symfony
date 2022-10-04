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
    '--no-ansi',
];
$argc = $_SERVER['argc'] = count($argv);

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/command_list.php';

?>
--EXPECTF--
Hello console 1.2.3

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display %s
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi%A
  -n, --no-interaction  Do not ask any interactive question
  -e, --env=ENV         The Environment name. [default: "prod"]
      --no-debug        Switches off debug mode.
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:%A
  help        Display%S help for a command
  list        List%S commands
  my_command  Hello description
