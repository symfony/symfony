--TEST--
Test that rendered exceptions are not logged a second time to STDERR in CLI
--INI--
display_errors=0
log_errors=1
error_log=
--FILE--
<?php

namespace Symfony\Component\ErrorHandler;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$_SERVER['NO_COLOR'] = '1';
ErrorHandler::register();

throw new \RuntimeException('foo');
?>
--EXPECTF--
RuntimeException {%S
  #message: "foo"
  #code: 0
  #file: "%s"
  #line: %d
}
