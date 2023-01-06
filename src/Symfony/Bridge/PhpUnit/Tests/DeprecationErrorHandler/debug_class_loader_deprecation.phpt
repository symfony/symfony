--TEST--
Test that a deprecation from the DebugClassLoader triggered by an app class extending a vendor one is considered direct.
--FILE--
<?php

$k = 'SYMFONY_DEPRECATIONS_HELPER';
putenv($k.'='.$_SERVER[$k] = $_ENV[$k] = 'max[total]=0');
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

\Symfony\Component\ErrorHandler\DebugClassLoader::enable();
new \App\Services\ExtendsDeprecatedFromVendor();

?>
--EXPECTF--
Remaining direct deprecation notices (1)

  1x: The "App\Services\ExtendsDeprecatedFromVendor" class extends "fcy\lib\DeprecatedClass" that is deprecated.
    1x in DebugClassLoader::loadClass from Symfony\Component\ErrorHandler
