--TEST--
Test that a deprecation from code in a PSR-0 fallback dir is classified as "self"
--FILE--
<?php

$k = 'SYMFONY_DEPRECATIONS_HELPER';
putenv($k.'='.$_SERVER[$k] = $_ENV[$k] = 'max[self]=0');
putenv('ANSICON');
putenv('ConEmuANSI');
putenv('TERM');
putenv('SYMFONY_DEPRECATIONS_SERIALIZE');

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
require __DIR__.'/fake_vendor_fallback_psr4/autoload.php';

(new \App\Services\FallbackDirService())->selfDeprecation();

?>
--EXPECTF--
Remaining self deprecation notices (1)

  1x: Since FallbackApp 1.0: selfDeprecation is deprecated.
    1x in FallbackDirService::selfDeprecation from App\Services

