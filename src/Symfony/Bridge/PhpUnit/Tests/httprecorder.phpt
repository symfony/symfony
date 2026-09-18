--TEST--
--SKIPIF--
<?php
if (!getenv('SYMFONY_PHPUNIT_VERSION') || version_compare(getenv('SYMFONY_PHPUNIT_VERSION'), '10', '<')) echo 'Skipping on PHPUnit < 10';
--FILE--
<?php
$phpunit = \sprintf('php %s/simple-phpunit.php -c %s/Fixtures/httprecorder/phpunit-with-recorder.xml.dist', getenv('SYMFONY_SIMPLE_PHPUNIT_BIN_DIR'), __DIR__);

passthru('NO_COLOR=1 '.$phpunit);
echo PHP_EOL;
passthru('NO_COLOR=1 SYMFONY_HTTP_RECORDER=nope '.$phpunit.' --filter testClassLevelRecord');
echo PHP_EOL;
passthru(\sprintf('NO_COLOR=1 php %s/simple-phpunit.php -c %s/Fixtures/httprecorder/phpunit-with-recorder-directory.xml.dist', getenv('SYMFONY_SIMPLE_PHPUNIT_BIN_DIR'), __DIR__));
echo PHP_EOL;

$var = __DIR__.'/Fixtures/httprecorder/var';
@mkdir($var);
file_put_contents($var.'/skipped.har', 'kept');

try {
    passthru('NO_COLOR=1 SYMFONY_HTTP_RECORDER=record '.$phpunit.' '.__DIR__.'/Fixtures/httprecorder/tests/RecordMode.php');
    echo PHP_EOL, file_get_contents($var.'/skipped.har'), PHP_EOL;
    echo json_decode(file_get_contents($var.'/recorded.har'), true)['log']['entries'][0]['response']['content']['text'], PHP_EOL;
} finally {
    array_map(unlink(...), glob($var.'/*'));
    rmdir($var);
}
--EXPECTF--
PHPUnit %s

Runtime:       PHP %s
Configuration: %s/src/Symfony/Bridge/PhpUnit/Tests/Fixtures/httprecorder/phpunit-with-recorder.xml.dist

.....                                                               5 / 5 (100%)

Time: %s, Memory: %s

There was 1 PHPUnit test runner warning:

1) No recorded response for GET https://example.com/missing?access_token=%5BREDACTED%5D in "Symfony\Bridge\PhpUnit\Tests\Fixtures\httprecorder\tests\HttpRecorded::testMissIsReportedEvenWhenCaught": run the test with SYMFONY_HTTP_RECORDER=missing to record it.

OK, but there were issues!
Tests: 5, Assertions: 6, PHPUnit Warnings: 1.

PHPUnit %s

Runtime:       PHP %s
Configuration: %s/src/Symfony/Bridge/PhpUnit/Tests/Fixtures/httprecorder/phpunit-with-recorder.xml.dist

.                                                                   1 / 1 (100%)

Time: %s, Memory: %s

There was 1 PHPUnit test runner warning:

1) Invalid value "nope" for the SYMFONY_HTTP_RECORDER env var, expected one of "replay", "record", "missing", "passthrough": HTTP calls are replayed.

OK, but there were issues!
Tests: 1, Assertions: 1, PHPUnit Warnings: 1.

PHPUnit %s

Runtime:       PHP %s
Configuration: %s/src/Symfony/Bridge/PhpUnit/Tests/Fixtures/httprecorder/phpunit-with-recorder-directory.xml.dist

.                                                                   1 / 1 (100%)

Time: %s, Memory: %s

OK (1 test, 1 assertion)

PHPUnit %s

Runtime:       PHP %s
Configuration: %s/src/Symfony/Bridge/PhpUnit/Tests/Fixtures/httprecorder/phpunit-with-recorder.xml.dist

S.                                                                  2 / 2 (100%)

Time: %s, Memory: %s

OK, but some tests were skipped!
Tests: 2, Assertions: 1, Skipped: 1.

kept
live
