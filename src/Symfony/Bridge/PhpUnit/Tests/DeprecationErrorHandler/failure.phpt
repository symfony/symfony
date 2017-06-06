--TEST--
Test DeprecationErrorHandler failure
--FILE--
<?php
namespace Symfony\Bridge\PhpUnit;
function error_get_last()
{
    return array(
        'type' => E_ERROR,
        'message' => 'failed to load flux capacitor',
        'file' => '/wherever/the/deprecation_handler_is',
        'line' => 42
    );
}

putenv('SYMFONY_DEPRECATIONS_HELPER');
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

trigger_error('test failure', E_USER_DEPRECATED);
set_error_handler('var_dump');

--EXPECTF--
THE ERROR HANDLER HAS CHANGED!
array (
  'type' => 1,
  'message' => 'failed to load flux capacitor',
  'file' => '/wherever/the/deprecation_handler_is',
  'line' => 42,
)

Other deprecation notices (1)

test failure: 1x
