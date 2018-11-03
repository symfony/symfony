--TEST--
Test DebugClassLoader with previously loaded parents
--FILE--
<?php

namespace Symfony\Component\Debug\Tests\Fixtures;

use Symfony\Component\Debug\DebugClassLoader;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

class_exists(FinalMethod::class);

set_error_handler(function ($type, $msg) { echo $msg, "\n"; });

DebugClassLoader::enable();

class_exists(ExtendedFinalMethod::class);

?>
--EXPECTF--
The "Symfony\Component\Debug\Tests\Fixtures\FinalMethod::finalMethod()" method is considered final. It may change without further notice as of its next major version. You should not extend it from "Symfony\Component\Debug\Tests\Fixtures\ExtendedFinalMethod".
The "Symfony\Component\Debug\Tests\Fixtures\FinalMethod::finalMethod2()" method is considered final. It may change without further notice as of its next major version. You should not extend it from "Symfony\Component\Debug\Tests\Fixtures\ExtendedFinalMethod".
