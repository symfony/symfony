--TEST--
Test ErrorHandler in case of fatal error
--SKIPIF--
<?php if (!extension_loaded("symfony_debug")) print "skip"; ?>
--FILE--
<?php

namespace Psr\Log;

class LogLevel
{
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';
}

namespace Symfony\Component\Debug;

$dir = __DIR__.'/../../../';
require $dir.'ErrorHandler.php';
require $dir.'Exception/FatalErrorException.php';
require $dir.'Exception/UndefinedFunctionException.php';
require $dir.'FatalErrorHandler/FatalErrorHandlerInterface.php';
require $dir.'FatalErrorHandler/ClassNotFoundFatalErrorHandler.php';
require $dir.'FatalErrorHandler/UndefinedFunctionFatalErrorHandler.php';
require $dir.'FatalErrorHandler/UndefinedMethodFatalErrorHandler.php';

function bar()
{
    foo();
}

function foo()
{
    notexist();
}

$handler = ErrorHandler::register();
$handler->setExceptionHandler('print_r');

if (function_exists('xdebug_disable')) {
    xdebug_disable();
}

bar();
?>
--EXPECTF--
Fatal error: Call to undefined function Symfony\Component\Debug\notexist() in %s on line %d
Symfony\Component\Debug\Exception\UndefinedFunctionException Object
(
    [message:protected] => Attempted to call function "notexist" from namespace "Symfony\Component\Debug".
    [string:Exception:private] => 
    [code:protected] => 0
    [file:protected] => %s
    [line:protected] => %d
    [trace:Exception:private] => Array
        (
            [0] => Array
                (
%A                    [function] => Symfony\Component\Debug\foo
%A                    [args] => Array
                        (
                        )

                )

            [1] => Array
                (
%A                    [function] => Symfony\Component\Debug\bar
%A                    [args] => Array
                        (
                        )

                )
%A
        )

    [previous:Exception:private] => 
    [severity:protected] => 1
)