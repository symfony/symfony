--TEST--
Test deprecation types with trigger_deprecation
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

(new \App\Services\AppService())->selfDeprecation(true);
(new \App\Services\AppService())->directDeprecation(true);
(new \App\Services\AppService())->indirectDeprecation(true);
trigger_deprecation('foo/bar', '2.0', 'func is deprecated, use new instead.');
?>
--EXPECTF--
Remaining self deprecation notices (1)

  1x: Since App 3.0: selfDeprecation is deprecated, use selfDeprecation_new instead.
    1x in AppService::selfDeprecation from App\Services

Remaining direct deprecation notices (1)

  1x: Since acme/lib 3.0: deprecatedApi is deprecated, use deprecatedApi_new instead.
    1x in AppService::directDeprecation from App\Services

Remaining indirect deprecation notices (1)

  1x: Since bar/lib 3.0: deprecatedApi is deprecated, use deprecatedApi_new instead.
    1x in AppService::indirectDeprecation from App\Services

Other deprecation notices (1)

  1x: Since foo/bar 2.0: func is deprecated, use new instead.
