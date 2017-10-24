--TEST--
Test DeprecationErrorHandler in weak mode
--FILE--
<?php

putenv('SYMFONY_DEPRECATIONS_HELPER=/foo/');
putenv('ANSICON');
putenv('ConEmuANSI');
putenv('TERM');

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
// Fake class to ensure bootstrap.php calls DeprecationErrorHandler::register().
class PHPUnit_TextUI_Command
{

}
require  $vendor.'/vendor/autoload.php';
require_once __DIR__.'/../../bootstrap.php';

@trigger_error('root deprecation', E_USER_DEPRECATED);

class FooTestCase
{
    public function testLegacyFoo()
    {
        @trigger_error('silenced foo deprecation', E_USER_DEPRECATED);
        trigger_error('unsilenced foo deprecation', E_USER_DEPRECATED);
    }
}

$foo = new FooTestCase();
$foo->testLegacyFoo();

?>
--EXPECTF--
Legacy deprecation triggered by FooTestCase::testLegacyFoo:
silenced foo deprecation
Stack trace:
#%A(%d): FooTestCase->testLegacyFoo()
#%d {main}

