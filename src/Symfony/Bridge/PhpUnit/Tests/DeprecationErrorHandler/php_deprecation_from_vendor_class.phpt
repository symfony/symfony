--TEST--
Test that a PHP deprecation from a vendor class autoload is considered indirect.
--SKIPIF--
<?php if (\PHP_VERSION_ID < 80100) echo 'skip'; ?>
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
new \acme\lib\PhpDeprecation();

?>
--EXPECTF--
Remaining indirect deprecation notices (1)

  1x: acme\lib\PhpDeprecation implements the Serializable interface, which is deprecated. Implement __serialize() and __unserialize() instead (or in addition, if support for old PHP versions is necessary)
    1x in DebugClassLoader::loadClass from Symfony\Component\ErrorHandler
