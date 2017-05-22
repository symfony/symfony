--TEST--
Test DeprecationErrorHandler in weak vendors mode on vendor file
--FILE--
<?php

putenv('SYMFONY_DEPRECATIONS_HELPER=weak_vendors');
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

?>
--EXPECTF--
Unsilenced deprecation notices (2)

Remaining vendor deprecation notices (1)

Legacy deprecation notices (1)

Other deprecation notices (1)
