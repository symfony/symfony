--TEST--
Test DeprecationErrorHandler in weak mode
--FILE--
<?php

putenv('SYMFONY_DEPRECATIONS_HELPER=disabled');
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

echo (int) set_error_handler('var_dump');
echo (int) class_exists('Symfony\Bridge\PhpUnit\DeprecationErrorHandler', false);

?>
--EXPECTF--
00
