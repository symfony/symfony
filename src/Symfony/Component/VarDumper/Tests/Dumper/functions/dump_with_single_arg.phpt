--TEST--
Test dump() with one arg doesn't show line number
--FILE--
<?php

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

dump('foo');

--EXPECT--
"foo"
