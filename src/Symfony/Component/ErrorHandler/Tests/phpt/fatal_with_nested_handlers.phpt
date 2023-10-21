--TEST--
Test catching fatal errors when handlers are nested
--FILE--
<?php

namespace Symfony\Component\ErrorHandler;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

Debug::enable();
ini_set('display_errors', 0);

$eHandler = set_error_handler('var_dump');
$xHandler = set_exception_handler('var_dump');

var_dump([
    $eHandler[0] === $xHandler[0] ? 'Error and exception handlers do match' : 'Error and exception handlers are different',
]);

$eHandler[0]->setExceptionHandler('print_r');

if (true) {
    class Broken implements \JsonSerializable
    {
    }
}

?>
--EXPECTF--
array(1) {
  [0]=>
  string(37) "Error and exception handlers do match"
}
object(Symfony\Component\ErrorHandler\Error\FatalError)#%d (%d) {
  ["message":protected]=>
  string(186) "Error: Class Symfony\Component\ErrorHandler\Broken contains 1 abstract method and must therefore be declared abstract or implement the remaining methods (JsonSerializable::jsonSerialize)"
%a
  ["error":"Symfony\Component\ErrorHandler\Error\FatalError":private]=>
  array(4) {
    ["type"]=>
    int(1)
    ["message"]=>
    string(179) "Class Symfony\Component\ErrorHandler\Broken contains 1 abstract method and must therefore be declared abstract or implement the remaining methods (JsonSerializable::jsonSerialize)"
    ["file"]=>
    string(%d) "%s"
    ["line"]=>
    int(%d)
  }
}
