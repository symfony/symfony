--TEST--
Test DeprecationErrorHandler in default mode
--FILE--
<?php

$k = 'SYMFONY_DEPRECATIONS_HELPER';
unset($_SERVER[$k], $_ENV[$k]);
putenv($k);
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
        return array();
    }
}
EOPHP
);

class PHPUnit_Util_Test
{
    public static function getGroups()
    {
        return array();
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
    exit('I get precedence over any exit statements inside the deprecation error handler.');
});

?>
--EXPECTF--
Unsilenced deprecation notices (3)

  2x: unsilenced foo deprecation
    2x in FooTestCase::testLegacyFoo

  1x: unsilenced bar deprecation
    1x in FooTestCase::testNonLegacyBar

Legacy deprecation notices (1)

Other deprecation notices (2)

  1x: root deprecation

  1x: silenced bar deprecation
    1x in FooTestCase::testNonLegacyBar

I get precedence over any exit statements inside the deprecation error handler.
