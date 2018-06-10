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

set_error_handler('var_dump');
set_exception_handler('var_dump');

ErrorHandler::register(null, false);

if (true) {
    class foo extends missing
    {
    }
}

?>
--EXPECTF--
Fatal error: Class 'Symfony\Component\Debug\missing' not found in %s on line %d
object(Symfony\Component\Debug\Exception\ClassNotFoundException)#%d (8) {
  ["message":protected]=>
  string(131) "Attempted to load class "missing" from namespace "Symfony\Component\Debug".
Did you forget a "use" statement for another namespace?"
  ["string":"Exception":private]=>
  string(0) ""
  ["code":protected]=>
  int(0)
  ["file":protected]=>
  string(%d) "%s"
  ["line":protected]=>
  int(%d)
  ["trace":"Exception":private]=>
  array(0) {
  }
  ["previous":"Exception":private]=>
  NULL
  ["severity":protected]=>
  int(1)
}
