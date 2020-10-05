--TEST--
Test DeprecationErrorHandler with multiple autoload files
--FILE--
<?php

$k = 'SYMFONY_DEPRECATIONS_HELPER';
putenv($k.'='.$_SERVER[$k] = $_ENV[$k] = 'max[self]=0');
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
require __DIR__.'/fake_vendor_bis/autoload.php';

(new \App\Services\AppService())->directDeprecations();
?>
--EXPECTF--
Remaining direct deprecation notices (2)

  1x: Since acme/lib 3.0: deprecatedApi is deprecated, use deprecatedApi_new instead.
    1x in AppService::directDeprecations from App\Services

  1x: deprecatedApi from foo is deprecated! You should stop relying on it!
    1x in AppService::directDeprecations from App\Services
