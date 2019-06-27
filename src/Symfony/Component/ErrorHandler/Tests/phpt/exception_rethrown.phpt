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
        public function log($level, $message, array $context = [])
        {
            echo $message, "\n";
        }
    }
}

set_exception_handler(function ($e) { echo 123; throw $e; });
ErrorHandler::register()->setDefaultLogger(new TestLogger());
ini_set('display_errors', 1);

throw new \Exception('foo');
?>
--EXPECTF--
Uncaught Exception: foo
123
Fatal error: Uncaught %s:25
Stack trace:
%a
