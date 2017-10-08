--TEST--
Test DeprecationErrorHandler in weak vendors mode on a non vendor file
--FILE--
<?php

putenv('SYMFONY_DEPRECATIONS_HELPER=weak_vendors');
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

?>
--EXPECTF--
Unsilenced deprecation notices (3)

unsilenced foo deprecation: 2x
    2x in FooTestCase::testLegacyFoo

unsilenced bar deprecation: 1x
    1x in FooTestCase::testNonLegacyBar

Remaining deprecation notices (1)

silenced bar deprecation: 1x
    1x in FooTestCase::testNonLegacyBar

Legacy deprecation notices (1)

Other deprecation notices (1)

root deprecation: 1x

