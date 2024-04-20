--TEST--
Test dd() with named args show label
--FILE--
<?php
putenv('NO_COLOR=1');

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

dd(label2: "dd() with label");

--EXPECT--
label2 "dd() with label"
