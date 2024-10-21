--TEST--
Test ExpectDeprecationTrait failing tests
--SKIPIF--
<?php if (!getenv('SYMFONY_PHPUNIT_VERSION') || version_compare(getenv('SYMFONY_PHPUNIT_VERSION'), '10.0', '>=')) die('Skipping on PHPUnit 10+');
--FILE--
<?php
$test =  realpath(__DIR__.'/FailTests/ExpectDeprecationTraitTestFail.php');
passthru('php '.getenv('SYMFONY_SIMPLE_PHPUNIT_BIN_DIR').'/simple-phpunit.php --colors=never '.$test);
?>
--EXPECTF--
PHPUnit %s

%ATesting Symfony\Bridge\PhpUnit\Tests\FailTests\ExpectDeprecationTraitTestFail
FF                                                                  2 / 2 (100%)

Time: %s, Memory: %s

There were 2 failures:

1) Symfony\Bridge\PhpUnit\Tests\FailTests\ExpectDeprecationTraitTestFail::testOne
Failed asserting that string matches format description.
--- Expected
+++ Actual
@@ @@
 @expectedDeprecation:
-%A  foo
+  bar

2) Symfony\Bridge\PhpUnit\Tests\FailTests\ExpectDeprecationTraitTestFail::testOneInIsolation
Failed asserting that string matches format description.
--- Expected
+++ Actual
@@ @@
 @expectedDeprecation:
-%A  foo
+  bar

FAILURES!
Tests: 2, Assertions: 2, Failures: 2.
