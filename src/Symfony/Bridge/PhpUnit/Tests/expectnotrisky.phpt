--TEST--
Test NoAssertionsTestNotRisky not risky test
--SKIPIF--
<?php if ('\\' === DIRECTORY_SEPARATOR && !extension_loaded('mbstring')) die('Skipping on Windows without mbstring');
--FILE--
<?php
$test =  realpath(__DIR__.'/FailTests/NoAssertionsTestNotRisky.php');
passthru('php '.getenv('SYMFONY_SIMPLE_PHPUNIT_BIN_DIR').'/simple-phpunit.php --fail-on-risky --colors=never '.$test);
?>
--EXPECTF--
PHPUnit %s

%ATesting Symfony\Bridge\PhpUnit\Tests\FailTests\NoAssertionsTestNotRisky
.                                                                   1 / 1 (100%)

Time: %s, Memory: %s

OK (1 test, 0 assertions)
