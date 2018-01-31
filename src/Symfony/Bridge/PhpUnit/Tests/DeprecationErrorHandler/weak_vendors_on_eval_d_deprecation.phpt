--TEST--
Test DeprecationErrorHandler in weak vendors mode on eval()'d deprecation
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
eval("@trigger_error('who knows where I come from?', E_USER_DEPRECATED);")

?>
--EXPECTF--

Other deprecation notices (1)

  1x: who knows where I come from?
