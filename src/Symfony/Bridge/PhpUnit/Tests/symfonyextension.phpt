--TEST--
--SKIPIF--
<?php
if (!getenv('SYMFONY_PHPUNIT_VERSION') || version_compare(getenv('SYMFONY_PHPUNIT_VERSION'), '10', '<')) echo 'Skipping on PHPUnit < 10';
--FILE--
<?php
passthru(\sprintf('NO_COLOR=1 php %s/simple-phpunit.php -c %s/Fixtures/symfonyextension/phpunit-with-extension.xml.dist %s/SymfonyExtension.php', getenv('SYMFONY_SIMPLE_PHPUNIT_BIN_DIR'), __DIR__, __DIR__));
echo PHP_EOL;
passthru(\sprintf('NO_COLOR=1 php %s/simple-phpunit.php -c %s/Fixtures/symfonyextension/phpunit-with-extension.xml.dist %s/SymfonyExtensionWithManualRegister.php', getenv('SYMFONY_SIMPLE_PHPUNIT_BIN_DIR'), __DIR__, __DIR__));
--EXPECTF--
PHPUnit %s

Runtime:       PHP %s
Configuration: %s/src/Symfony/Bridge/PhpUnit/Tests/Fixtures/symfonyextension/phpunit-with-extension.xml.dist

D................................................................ 65 / 76 ( 85%)
...........                                                       76 / 76 (100%)

Time: %s, Memory: %s

OK, but there were issues!
Tests: 76, Assertions: 76, Deprecations: 1.

PHPUnit %s

Runtime:       PHP %s
Configuration: %s/src/Symfony/Bridge/PhpUnit/Tests/Fixtures/symfonyextension/phpunit-with-extension.xml.dist

....                                                                4 / 4 (100%)

Time: %s, Memory: %s

OK (4 tests, 4 assertions)
