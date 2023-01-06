--TEST--
Test DeprecationErrorHandler in baseline mode
--FILE--
<?php
$filename = tempnam(sys_get_temp_dir(), 'sf-');
$baseline = [[
  'location' => 'FooTestCase::testLegacyFoo',
  'message' => 'silenced foo deprecation',
  'count' => 1,
],
[
  'location' => 'FooTestCase::testNonLegacyBar',
  'message' => 'silenced bar deprecation',
  'count' => 1,
],
[
  'location' => 'procedural code',
  'message' => 'root deprecation',
  'count' => 1,
]];
file_put_contents($filename, json_encode($baseline, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

$k = 'SYMFONY_DEPRECATIONS_HELPER';
unset($_SERVER[$k], $_ENV[$k]);
putenv($k.'='.$_SERVER[$k] = $_ENV[$k] = 'baselineFile=' . urlencode($filename));
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
    }

    public function testNonLegacyBar()
    {
        @trigger_error('silenced bar deprecation', E_USER_DEPRECATED);
    }
}

$foo = new FooTestCase();
$foo->testLegacyFoo();
$foo->testNonLegacyBar();
print "Cannot test baselineFile contents because it is generated in a shutdown function registered by another shutdown function."
?>
--EXPECT--
Cannot test baselineFile contents because it is generated in a shutdown function registered by another shutdown function.
Legacy deprecation notices (1)
