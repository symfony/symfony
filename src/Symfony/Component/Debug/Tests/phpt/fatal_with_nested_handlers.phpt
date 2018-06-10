--TEST--
Test catching fatal errors when handlers are nested
--FILE--
<?php

namespace Symfony\Component\Debug;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

Debug::enable();
ini_set('display_errors', 0);

$eHandler = set_error_handler('var_dump');
$xHandler = set_exception_handler('var_dump');

var_dump(array(
    $eHandler[0] === $xHandler[0] ? 'Error and exception handlers do match' : 'Error and exception handlers are different',
));

$eHandler[0]->setExceptionHandler('print_r');

if (true) {
    class Broken implements \Serializable
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
  string(199) "Error: Class Symfony\Component\Debug\Broken contains 2 abstract methods and must therefore be declared abstract or implement the remaining methods (Serializable::serialize, Serializable::unserialize)"
%a
}
