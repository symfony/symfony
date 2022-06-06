--TEST--
Test NoAssertionsTestRisky risky test
--SKIPIF--
<?php if ('\\' === DIRECTORY_SEPARATOR && !extension_loaded('mbstring')) die('Skipping on Windows without mbstring');
--FILE--
<?php
$test =  realpath(__DIR__.'/FailTests/NoAssertionsTestRisky.php');
passthru('php '.getenv('SYMFONY_SIMPLE_PHPUNIT_BIN_DIR').'/simple-phpunit.php --fail-on-risky --colors=never '.$test);
?>
--EXPECTF--
PHPUnit %s

%ATesting Symfony\Bridge\PhpUnit\Tests\FailTests\NoAssertionsTestRisky
R.                                                                  2 / 2 (100%)

Time: %s, Memory: %s

There was 1 risky test:

1) Symfony\Bridge\PhpUnit\Tests\FailTests\NoAssertionsTestRisky::testOne
This test is annotated with "@doesNotPerformAssertions", but performed 1 assertions

OK, but incomplete, skipped, or risky tests!
Tests: 2, Assertions: 1, Risky: 1.
