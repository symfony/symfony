--TEST--
Test catching fatal errors when handlers are nested
--FILE--
<?php

namespace Symfony\Component\Debug;

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
object(Symfony\Component\Debug\Exception\FatalErrorException)#%d (%d) {
  ["message":protected]=>
  string(179) "Error: Class Symfony\Component\Debug\Broken contains 1 abstract method and must therefore be declared abstract or implement the remaining methods (JsonSerializable::jsonSerialize)"
%a
}
