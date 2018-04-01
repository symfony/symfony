--TEST--
Test DeprecationErrorHandler in weak mode
--FILE--
<?php

putenv('SYMPHONY_DEPRECATIONS_HELPER=disabled');
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

echo (int) set_error_handler('var_dump');
echo (int) class_exists('Symphony\Bridge\PhpUnit\DeprecationErrorHandler', false);

?>
--EXPECTF--
00
