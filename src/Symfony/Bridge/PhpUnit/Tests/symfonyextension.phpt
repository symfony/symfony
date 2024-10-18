--TEST--
--SKIPIF--
<?php
if (!getenv('SYMFONY_PHPUNIT_VERSION') || version_compare(getenv('SYMFONY_PHPUNIT_VERSION'), '10', '<')) die('Skipping on PHPUnit < 10');
--FILE--
<?php
passthru(\sprintf('NO_COLOR=1 php %s/simple-phpunit.php -c %s/Fixtures/symfonyextension/phpunit-with-extension.xml.dist %s/SymfonyExtension.php', getenv('SYMFONY_SIMPLE_PHPUNIT_BIN_DIR'), __DIR__, __DIR__));
--EXPECTF--
PHPUnit %s

Runtime:       PHP %s
Configuration: %s/src/Symfony/Bridge/PhpUnit/Tests/Fixtures/symfonyextension/phpunit-with-extension.xml.dist

D.............................................                    46 / 46 (100%)

Time: %s, Memory: %s

OK, but there were issues!
Tests: 46, Assertions: 46, Deprecations: 1.
