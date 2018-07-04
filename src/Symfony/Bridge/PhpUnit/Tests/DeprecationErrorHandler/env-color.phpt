--TEST--
Test DeprecationErrorHandler in forced color mode
--FILE--
<?php

putenv('SYMFONY_DEPRECATIONS_HELPER');
putenv('ANSICON');
putenv('ConEmuANSI');
putenv('TERM');
putenv('ANSI=1');

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
define('PHPUNIT_COMPOSER_INSTALL', $vendor.'/vendor/autoload.php');
require PHPUNIT_COMPOSER_INSTALL;
require_once __DIR__.'/../../bootstrap.php';

trigger_error('root deprecation', E_USER_DEPRECATED);

?>
--EXPECTREGEX--
.\[[0-9;]+mOther deprecation notices \(1\).\[0m

  1x: root deprecation
