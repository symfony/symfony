--TEST--
Test DeprecationErrorHandler in default mode
--FILE--
<?php

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

@trigger_error('root deprecation', E_USER_DEPRECATED);

eval(<<<'EOPHP'
namespace PHPUnit\Util;

class Test
{
    public static function getGroups()
    {
        return [];
    }
}
EOPHP
);

class PHPUnit_Util_Test
{
    public static function getGroups()
    {
        return [];
    }
}

class FooTestCase
{
    public function testLegacyFoo()
    {
        @trigger_error('silenced foo deprecation', E_USER_DEPRECATED);
        trigger_error('unsilenced foo deprecation', E_USER_DEPRECATED);
        trigger_error('unsilenced foo deprecation', E_USER_DEPRECATED);
    }

    public function testNonLegacyBar()
    {
        @trigger_error('silenced bar deprecation', E_USER_DEPRECATED);
        trigger_error('unsilenced bar deprecation', E_USER_DEPRECATED);
    }
}

$foo = new FooTestCase();
$foo->testLegacyFoo();
$foo->testNonLegacyBar();

register_shutdown_function(function () {
    @trigger_error('root deprecation during shutdown', E_USER_DEPRECATED);
});

?>
--EXPECTF--
Unsilenced deprecation notices (3)

  2x: unsilenced foo deprecation
    2x in FooTestCase::testLegacyFoo

  1x: unsilenced bar deprecation
    1x in FooTestCase::testNonLegacyBar

Remaining deprecation notices (1)

  1x: silenced bar deprecation
    1x in FooTestCase::testNonLegacyBar

Legacy deprecation notices (1)

Other deprecation notices (1)

  1x: root deprecation

Shutdown-time deprecations:

Other deprecation notices (1)

  1x: root deprecation during shutdown
