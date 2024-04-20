--TEST--
Test dump() with multiple args shows line number
--FILE--
<?php
putenv('NO_COLOR=1');

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

dump(null, 1, 'foo');

--EXPECT--
1 null
2 1
3 "foo"
