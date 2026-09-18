--TEST--
Test using the fatal error backtrace collected by PHP
--SKIPIF--
<?php if (\PHP_VERSION_ID < 80500) echo 'Skipped: PHP >= 8.5 required.'; ?>
--INI--
display_errors=0
fatal_error_backtraces=1
--FILE--
<?php

use Symfony\Component\ErrorHandler\ErrorHandler;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$handler = ErrorHandler::register();
$handler->setExceptionHandler(static function (\Throwable $e): void {
    echo $e::class, "\n";

    foreach ($e->getTrace() as $frame) {
        echo $frame['function'], "\n";
    }
});

class Foo
{
}

function declareFoo(): void
{
    eval('class Foo {}');
}

function callDeclareFoo(): void
{
    declareFoo();
}

callDeclareFoo();

?>
--EXPECT--
Symfony\Component\ErrorHandler\Error\FatalError
eval
declareFoo
callDeclareFoo
