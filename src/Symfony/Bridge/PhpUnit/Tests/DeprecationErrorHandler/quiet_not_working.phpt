--TEST--
Test DeprecationErrorHandler with quiet output (not working)
--FILE--
<?php

$k = 'SYMFONY_DEPRECATIONS_HELPER';
putenv($k.'='.$_SERVER[$k] = $_ENV[$k] = 'quiet[]=self&quiet[]=direct&quiet[]=indirect&quiet[]=other');
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

Remaining direct deprecation notices (1)

Remaining indirect deprecation notices (1)

Other deprecation notices (1)
