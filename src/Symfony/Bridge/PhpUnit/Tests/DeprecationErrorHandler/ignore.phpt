--TEST--
Test DeprecationErrorHandler with an ignoreFile
--FILE--
<?php
$filename = tempnam(sys_get_temp_dir(), 'sf-');
$ignorePatterns = [
  '# A comment line and, below, an empty comment line and an empty line that should be interpreted as a comment.',
  '#',
  '',
  '/^ignored .* deprecation/',
];
file_put_contents($filename, implode("\n", $ignorePatterns));

$k = 'SYMFONY_DEPRECATIONS_HELPER';
unset($_SERVER[$k], $_ENV[$k]);
putenv($k.'='.$_SERVER[$k] = $_ENV[$k] = 'ignoreFile=' . urlencode($filename));
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
@trigger_error('ignored root deprecation', E_USER_DEPRECATED);

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
        @trigger_error('ignored foo deprecation', E_USER_DEPRECATED);
        @trigger_error('not ignored foo deprecation', E_USER_DEPRECATED);
    }

    public function testNonLegacyBar()
    {
        @trigger_error('ignored bar deprecation', E_USER_DEPRECATED);
        @trigger_error('not ignored bar deprecation', E_USER_DEPRECATED);
    }
}

$foo = new FooTestCase();
$foo->testLegacyFoo();
$foo->testNonLegacyBar();
?>
--EXPECTF--
Legacy deprecation notices (1)

Other deprecation notices (2)

  1x: root deprecation

  1x: not ignored bar deprecation
    1x in FooTestCase::testNonLegacyBar
