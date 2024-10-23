--TEST--
--SKIPIF--
<?php
if (!getenv('SYMFONY_PHPUNIT_VERSION') || version_compare(getenv('SYMFONY_PHPUNIT_VERSION'), '10', '<')) die('Skipping on PHPUnit < 10');
--FILE--
<?php
passthru(\sprintf('NO_COLOR=1 php %s/simple-phpunit.php -c %s/Fixtures/symfonyextension/phpunit-without-extension.xml.dist %s/SymfonyExtension.php', getenv('SYMFONY_SIMPLE_PHPUNIT_BIN_DIR'), __DIR__, __DIR__));
--EXPECTF--
PHPUnit %s

Runtime:       PHP %s
Configuration: %s/src/Symfony/Bridge/PhpUnit/Tests/Fixtures/symfonyextension/phpunit-without-extension.xml.dist

FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF                    46 / 46 (100%)

Time: %s, Memory: %s

There were 46 failures:

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testExtensionOfFinalClass
Expected deprecation with message "The "Symfony\Bridge\PhpUnit\Tests\Fixtures\symfonyextension\src\FinalClass" class is considered final. It may change without further notice as of its next major version. You should not extend it from "Symfony\Bridge\PhpUnit\Tests\Fixtures\symfonyextension\src\ClassExtendingFinalClass"." was not triggered

%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testTimeMockIsRegistered with data set "test class namespace" ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testTimeMockIsRegistered with data set "namespace derived from test namespace" ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testTimeMockIsRegistered with data set "explicitly configured namespace" ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testMicrotimeMockIsRegistered with data set "test class namespace" ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testMicrotimeMockIsRegistered with data set "namespace derived from test namespace" ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testMicrotimeMockIsRegistered with data set "explicitly configured namespace" ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testSleepMockIsRegistered with data set "test class namespace" ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testSleepMockIsRegistered with data set "namespace derived from test namespace" ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testSleepMockIsRegistered with data set "explicitly configured namespace" ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testUsleepMockIsRegistered with data set "test class namespace" ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testUsleepMockIsRegistered with data set "namespace derived from test namespace" ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testUsleepMockIsRegistered with data set "explicitly configured namespace" ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDateMockIsRegistered with data set "test class namespace" ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDateMockIsRegistered with data set "namespace derived from test namespace" ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDateMockIsRegistered with data set "explicitly configured namespace" ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGmdateMockIsRegistered with data set "test class namespace" ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGmdateMockIsRegistered with data set "namespace derived from test namespace" ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGmdateMockIsRegistered with data set "explicitly configured namespace" ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testHrtimeMockIsRegistered with data set "test class namespace" ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testHrtimeMockIsRegistered with data set "namespace derived from test namespace" ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testHrtimeMockIsRegistered with data set "explicitly configured namespace" ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testCheckdnsrrMockIsRegistered with data set "test class namespace" ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testCheckdnsrrMockIsRegistered with data set "namespace derived from test namespace" ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testCheckdnsrrMockIsRegistered with data set "explicitly configured namespace" ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsCheckRecordMockIsRegistered with data set "test class namespace" ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsCheckRecordMockIsRegistered with data set "namespace derived from test namespace" ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsCheckRecordMockIsRegistered with data set "explicitly configured namespace" ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGetmxrrMockIsRegistered with data set "test class namespace" ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGetmxrrMockIsRegistered with data set "namespace derived from test namespace" ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGetmxrrMockIsRegistered with data set "explicitly configured namespace" ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsGetMxMockIsRegistered with data set "test class namespace" ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsGetMxMockIsRegistered with data set "namespace derived from test namespace" ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsGetMxMockIsRegistered with data set "explicitly configured namespace" ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbyaddrMockIsRegistered with data set "test class namespace" ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbyaddrMockIsRegistered with data set "namespace derived from test namespace" ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbyaddrMockIsRegistered with data set "explicitly configured namespace" ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbynameMockIsRegistered with data set "test class namespace" ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbynameMockIsRegistered with data set "namespace derived from test namespace" ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbynameMockIsRegistered with data set "explicitly configured namespace" ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbynamelMockIsRegistered with data set "test class namespace" ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbynamelMockIsRegistered with data set "namespace derived from test namespace" ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbynamelMockIsRegistered with data set "explicitly configured namespace" ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsGetRecordMockIsRegistered with data set "test class namespace" ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsGetRecordMockIsRegistered with data set "namespace derived from test namespace" ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsGetRecordMockIsRegistered with data set "explicitly configured namespace" ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

FAILURES!
Tests: 46, Assertions: 46, Failures: 46.
