--TEST--
Test catching fatal errors when handlers are nested
--INI--
display_errors=0
--FILE--
<?php

namespace Symfony\Component\ErrorHandler;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
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
--EXPECTF--
object(Symfony\Component\ErrorHandler\Error\ClassNotFoundError)#%d (7) {
  ["message":protected]=>
  string(138) "Attempted to load class "missing" from namespace "Symfony\Component\ErrorHandler".
Did you forget a "use" statement for another namespace?"
  ["string":"Error":private]=>
  string(0) ""
  ["code":protected]=>
  int(0)
  ["file":protected]=>
  string(%d) "%s"
  ["line":protected]=>
  int(%d)
  ["trace":"Error":private]=>
  array(%d) {%A}
  ["previous":"Error":private]=>
  NULL
}
