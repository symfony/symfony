--TEST--
Test DeprecationErrorHandler with log file
--FILE--
<?php
$filename = tempnam(sys_get_temp_dir(), 'sf-').uniqid();
$k = 'SYMFONY_DEPRECATIONS_HELPER';
putenv($k.'='.$_SERVER[$k] = $_ENV[$k] = 'logFile='.$filename);
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

class FooTestCase
{
    public function testLegacyFoo()
    {
        trigger_error('unsilenced foo deprecation', E_USER_DEPRECATED);
        trigger_error('unsilenced foo deprecation', E_USER_DEPRECATED);
    }

    public function testLegacyBar()
    {
        trigger_error('unsilenced bar deprecation', E_USER_DEPRECATED);
    }
}

@trigger_error('root deprecation', E_USER_DEPRECATED);

$foo = new FooTestCase();
$foo->testLegacyFoo();
$foo->testLegacyBar();

register_shutdown_function(function () use ($filename) {
    var_dump(file_get_contents($filename));
});
?>
--EXPECTF--
string(234) "
Unsilenced deprecation notices (3)

  2x: unsilenced foo deprecation
    2x in FooTestCase::testLegacyFoo

  1x: unsilenced bar deprecation
    1x in FooTestCase::testLegacyBar

Other deprecation notices (1)

  1x: root deprecation

"
