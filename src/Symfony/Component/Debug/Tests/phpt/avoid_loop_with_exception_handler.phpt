--TEST--
Test that, when handling a fatal, we don't create a loop with a third party exception handler installed after ours
--FILE--
<?php

namespace Symfony\Component\Debug;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

class ThirdPartyExceptionHandler
{
    private static $prevErrorHandler;
    private static $prevExceptionHandler;
    
    public static function register()
    {
        static::$prevErrorHandler = set_error_handler([__CLASS__, 'handleError']);
        static::$prevExceptionHandler = set_exception_handler([__CLASS__, 'handleException']);
    }

    public static function handleError($e)
    {
        echo 'Third party error handler' . PHP_EOL;
        echo 'Calling previous handler: ' . get_class(static::$prevErrorHandler[0]) . PHP_EOL;
        return call_user_func(static::$prevErrorHandler, $e);
    }

    public static function handleException($e)
    {
        echo 'Third party exception handler' . PHP_EOL;
        echo 'Calling previous handler: ' . get_class(static::$prevExceptionHandler[0]) . PHP_EOL;
        return call_user_func(static::$prevExceptionHandler, $e);
    }
}

ErrorHandler::register();
ThirdPartyExceptionHandler::register();
ini_set('display_errors', 1);

$a = null;
require 'inexistent_file.php';
?>
--EXPECTF--
Third party error handler
Calling previous handler: Symfony\Component\Debug\ErrorHandler
Third party exception handler
Calling previous handler: Symfony\Component\Debug\ErrorHandler
Uncaught Exception: foo
Fatal error: Uncaught %s
Stack trace:
%a
