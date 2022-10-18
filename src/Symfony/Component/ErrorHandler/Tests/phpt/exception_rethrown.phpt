--TEST--
Test rethrowing in custom exception handler
--FILE--
<?php

namespace Symfony\Component\ErrorHandler;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

if (true) {
    class TestLogger extends \Psr\Log\AbstractLogger
    {
        public function log($level, $message, array $context = []): void
        {
            if (0 !== strpos($message, 'Deprecated: ')) {
                echo 'LOG: ', $message, "\n";
            }
        }
    }
}

$_SERVER['NO_COLOR'] = '1';
set_exception_handler(function ($e) { echo "EHLO\n"; throw $e; });
ErrorHandler::register()->setDefaultLogger(new TestLogger());

throw new \Exception('foo');
?>
--EXPECTF--
LOG: Uncaught Exception: foo
EHLO
Exception {%S
  #message: "foo"
  #code: 0
  #file: "%s"
  #line: 27
}
