--TEST--
Single Application can be executed
--ARGS--
--help --no-ansi
--FILE--
<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

(new SingleCommandApplication())
    ->setName('My Super Command')
    ->setCode(function (InputInterface $input, OutputInterface $output): int {
        return 0;
    })
    ->run()
;
?>
--EXPECTF--
Usage:
  %s

Options:
  -h, --help            Display help for the given command. When no command is given display help for the %s command
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi|--no-ansi  Force (or disable --no-ansi) ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
