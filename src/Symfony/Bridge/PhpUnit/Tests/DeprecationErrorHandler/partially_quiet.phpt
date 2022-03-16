--TEST--
Test DeprecationErrorHandler quiet on everything but indirect deprecations
--FILE--
<?php

$k = 'SYMFONY_DEPRECATIONS_HELPER';
putenv($k.'='.$_SERVER[$k] = $_ENV[$k] = 'max[self]=0&quiet[]=unsilenced&quiet[]=direct&quiet[]=other');
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

Remaining indirect deprecation notices (1)

  1x: Since acme/lib 3.0: deprecatedApi is deprecated, use deprecatedApi_new instead.
    1x in SomeService::deprecatedApi from acme\lib

Legacy deprecation notices (2)

