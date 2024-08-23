--TEST--
Test dump() with "Accept: text/html" uses CLI dumper with CLI SAPI
--FILE--
<?php
putenv('NO_COLOR=1');

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$_SERVER['HTTP_ACCEPT'] = 'text/html';
dump('Test with HTML');
--EXPECT--
"Test with HTML"
