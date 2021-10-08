--TEST--
Test DeprecationErrorHandler quiet on everything but self/direct deprecations
--FILE--
<?php

$k = 'SYMFONY_DEPRECATIONS_HELPER';
putenv($k.'='.$_SERVER[$k] = $_ENV[$k] = 'max[self]=0&max[direct]=0&quiet[]=unsilenced&quiet[]=indirect&quiet[]=other');
putenv('ANSICON');
putenv('ConEmuANSI');
putenv('TERM');

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
define('PHPUNIT_COMPOSER_INSTALL', $vendor.'/vendor/autoload.php');
require PHPUNIT_COMPOSER_INSTALL;
require_once __DIR__.'/../../bootstrap.php';
require __DIR__.'/fake_vendor/autoload.php';
require __DIR__.'/fake_vendor/acme/lib/deprecation_riddled.php';
require __DIR__.'/fake_vendor/acme/outdated-lib/outdated_file.php';

?>
--EXPECTF--
Unsilenced deprecation notices (3)

Remaining direct deprecation notices (2)

  1x: root deprecation

  1x: silenced bar deprecation
    1x in FooTestCase::testNonLegacyBar

Remaining indirect deprecation notices (1)

Legacy deprecation notices (2)
