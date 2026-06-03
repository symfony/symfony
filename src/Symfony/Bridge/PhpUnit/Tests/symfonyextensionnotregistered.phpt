--TEST--
--SKIPIF--
<?php
if (!getenv('SYMFONY_PHPUNIT_VERSION') || version_compare(getenv('SYMFONY_PHPUNIT_VERSION'), '10', '<')) echo 'Skipping on PHPUnit < 10';
--FILE--
<?php
passthru(\sprintf('NO_COLOR=1 php %s/simple-phpunit.php -c %s/Fixtures/symfonyextension/phpunit-without-extension.xml.dist %s/SymfonyExtension.php', getenv('SYMFONY_SIMPLE_PHPUNIT_BIN_DIR'), __DIR__, __DIR__));
--EXPECTF--
PHPUnit %s

Runtime:       PHP %s
Configuration: %s/src/Symfony/Bridge/PhpUnit/Tests/Fixtures/symfonyextension/phpunit-without-extension.xml.dist

FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF 65 / 76 ( 85%)
FFFFFFFFFFF                                                       76 / 76 (100%)

Time: %s, Memory: %s

There were 76 failures:

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testExtensionOfFinalClass
Expected deprecation with message "The "Symfony\Bridge\PhpUnit\Tests\Fixtures\symfonyextension\src\FinalClass" class is considered final. It may change without further notice as of its next major version. You should not extend it from "Symfony\Bridge\PhpUnit\Tests\Fixtures\symfonyextension\src\ClassExtendingFinalClass"." was not triggered

%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testTimeMockIsRegistered%stest class namespace%s ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testTimeMockIsRegistered%snamespace derived from test namespace%s ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testTimeMockIsRegistered%sexplicitly configured namespace%s ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testTimeMockIsRegistered%sexplicitly configured namespace through attribute on class%s ('App\Foo')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testTimeMockIsRegistered%sexplicitly configured namespace through attribute on method%s ('App\Bar')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testMicrotimeMockIsRegistered%stest class namespace%s ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testMicrotimeMockIsRegistered%snamespace derived from test namespace%s ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testMicrotimeMockIsRegistered%sexplicitly configured namespace%s ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testMicrotimeMockIsRegistered%sexplicitly configured namespace through attribute on class%s ('App\Foo')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testMicrotimeMockIsRegistered%sexplicitly configured namespace through attribute on method%s ('App\Bar')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testSleepMockIsRegistered%stest class namespace%s ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testSleepMockIsRegistered%snamespace derived from test namespace%s ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testSleepMockIsRegistered%sexplicitly configured namespace%s ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testSleepMockIsRegistered%sexplicitly configured namespace through attribute on class%s ('App\Foo')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testSleepMockIsRegistered%sexplicitly configured namespace through attribute on method%s ('App\Bar')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testUsleepMockIsRegistered%stest class namespace%s ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testUsleepMockIsRegistered%snamespace derived from test namespace%s ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testUsleepMockIsRegistered%sexplicitly configured namespace%s ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testUsleepMockIsRegistered%sexplicitly configured namespace through attribute on class%s ('App\Foo')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testUsleepMockIsRegistered%sexplicitly configured namespace through attribute on method%s ('App\Bar')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDateMockIsRegistered%stest class namespace%s ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDateMockIsRegistered%snamespace derived from test namespace%s ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDateMockIsRegistered%sexplicitly configured namespace%s ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDateMockIsRegistered%sexplicitly configured namespace through attribute on class%s ('App\Foo')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDateMockIsRegistered%sexplicitly configured namespace through attribute on method%s ('App\Bar')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGmdateMockIsRegistered%stest class namespace%s ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGmdateMockIsRegistered%snamespace derived from test namespace%s ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGmdateMockIsRegistered%sexplicitly configured namespace%s ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGmdateMockIsRegistered%sexplicitly configured namespace through attribute on class%s ('App\Foo')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGmdateMockIsRegistered%sexplicitly configured namespace through attribute on method%s ('App\Bar')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testHrtimeMockIsRegistered%stest class namespace%s ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testHrtimeMockIsRegistered%snamespace derived from test namespace%s ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testHrtimeMockIsRegistered%sexplicitly configured namespace%s ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testHrtimeMockIsRegistered%sexplicitly configured namespace through attribute on class%s ('App\Foo')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testHrtimeMockIsRegistered%sexplicitly configured namespace through attribute on method%s ('App\Bar')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testCheckdnsrrMockIsRegistered%stest class namespace%s ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testCheckdnsrrMockIsRegistered%snamespace derived from test namespace%s ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testCheckdnsrrMockIsRegistered%sexplicitly configured namespace%s ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testCheckdnsrrMockIsRegistered%sexplicitly configured namespace through attribute on class%s ('App\Foo')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testCheckdnsrrMockIsRegistered%sexplicitly configured namespace through attribute on method%s ('App\Bar')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsCheckRecordMockIsRegistered%stest class namespace%s ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsCheckRecordMockIsRegistered%snamespace derived from test namespace%s ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsCheckRecordMockIsRegistered%sexplicitly configured namespace%s ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsCheckRecordMockIsRegistered%sexplicitly configured namespace through attribute on class%s ('App\Foo')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsCheckRecordMockIsRegistered%sexplicitly configured namespace through attribute on method%s ('App\Bar')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGetmxrrMockIsRegistered%stest class namespace%s ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGetmxrrMockIsRegistered%snamespace derived from test namespace%s ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGetmxrrMockIsRegistered%sexplicitly configured namespace%s ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGetmxrrMockIsRegistered%sexplicitly configured namespace through attribute on class%s ('App\Foo')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGetmxrrMockIsRegistered%sexplicitly configured namespace through attribute on method%s ('App\Bar')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsGetMxMockIsRegistered%stest class namespace%s ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsGetMxMockIsRegistered%snamespace derived from test namespace%s ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsGetMxMockIsRegistered%sexplicitly configured namespace%s ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsGetMxMockIsRegistered%sexplicitly configured namespace through attribute on class%s ('App\Foo')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsGetMxMockIsRegistered%sexplicitly configured namespace through attribute on method%s ('App\Bar')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbyaddrMockIsRegistered%stest class namespace%s ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbyaddrMockIsRegistered%snamespace derived from test namespace%s ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbyaddrMockIsRegistered%sexplicitly configured namespace%s ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbyaddrMockIsRegistered%sexplicitly configured namespace through attribute on class%s ('App\Foo')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbyaddrMockIsRegistered%sexplicitly configured namespace through attribute on method%s ('App\Bar')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbynameMockIsRegistered%stest class namespace%s ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbynameMockIsRegistered%snamespace derived from test namespace%s ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbynameMockIsRegistered%sexplicitly configured namespace%s ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbynameMockIsRegistered%sexplicitly configured namespace through attribute on class%s ('App\Foo')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbynameMockIsRegistered%sexplicitly configured namespace through attribute on method%s ('App\Bar')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbynamelMockIsRegistered%stest class namespace%s ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbynamelMockIsRegistered%snamespace derived from test namespace%s ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbynamelMockIsRegistered%sexplicitly configured namespace%s ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbynamelMockIsRegistered%sexplicitly configured namespace through attribute on class%s ('App\Foo')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testGethostbynamelMockIsRegistered%sexplicitly configured namespace through attribute on method%s ('App\Bar')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsGetRecordMockIsRegistered%stest class namespace%s ('Symfony\Bridge\PhpUnit\Tests')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsGetRecordMockIsRegistered%snamespace derived from test namespace%s ('Symfony\Bridge\PhpUnit')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsGetRecordMockIsRegistered%sexplicitly configured namespace%s ('App')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsGetRecordMockIsRegistered%sexplicitly configured namespace through attribute on class%s ('App\Foo')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

%d) Symfony\Bridge\PhpUnit\Tests\SymfonyExtension::testDnsGetRecordMockIsRegistered%sexplicitly configured namespace through attribute on method%s ('App\Bar')
Failed asserting that false is true.

%s/src/Symfony/Bridge/PhpUnit/Tests/SymfonyExtension.php:%d
%s/.phpunit/phpunit-%s/phpunit:%d

FAILURES!
Tests: 76, Assertions: 76, Failures: 76.
