--TEST--
Test DeprecationErrorHandler in weak vendors mode when calling deprecated api
--FILE--
<?php

putenv('SYMFONY_DEPRECATIONS_HELPER=weak_lagging_vendors');
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
eval(<<<'EOPHP'
namespace PHPUnit\Util;

class Test
{
    public static function getGroups()
    {
        return array();
    }
}
EOPHP
);
require __DIR__.'/fake_vendor/autoload.php';
require __DIR__.'/fake_vendor/acme/lib/SomeService.php';
$defraculator = new \Acme\Lib\SomeService();
$defraculator->deprecatedApi();


?>
--EXPECTF--
Remaining deprecation notices (1)

  1x: deprecatedApi is deprecated! You should stop relying on it!
    1x in SomeService::deprecatedApi from acme\lib

