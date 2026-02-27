--TEST--
Test dump() with "Accept: */*" uses HTML dumper
--FILE--
<?php
putenv('NO_COLOR=1');

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$_SERVER['HTTP_ACCEPT'] = '*/*';
dump('Test with wildcard');
--EXPECTF--
%a>Test with wildcard</%a
