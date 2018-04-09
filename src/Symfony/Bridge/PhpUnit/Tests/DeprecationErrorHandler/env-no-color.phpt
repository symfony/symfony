--TEST--
Test DeprecationErrorHandler in forced colorless mode
--FILE--
<?php

putenv('SYMFONY_DEPRECATIONS_HELPER');
putenv('ANSICON=true');
putenv('ConEmuANSI=ON');
putenv('TERM=xterm');
putenv('ANSI=0');

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
define('PHPUNIT_COMPOSER_INSTALL', $vendor.'/vendor/autoload.php');
require PHPUNIT_COMPOSER_INSTALL;
require_once __DIR__.'/../../bootstrap.php';

trigger_error('root deprecation', E_USER_DEPRECATED);

?>
--EXPECT--
Other deprecation notices (1)

  1x: root deprecation
