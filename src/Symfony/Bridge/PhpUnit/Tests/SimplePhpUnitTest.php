<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\TestCase;

class SimplePhpUnitTest extends TestCase
{
    private static $testFiles = [
        __DIR__.'/SimplePhpUnitTest/Modul1/phpunit.xml.dist',
        __DIR__.'/SimplePhpUnitTest/Modul2/phpunit.xml.dist',
    ];

    private $currentCwd;

    public static function setUpBeforeClass()
    {
        foreach (self::$testFiles as $testFile) {
            $renamedFile = str_replace('.xml.dist', '.txml.dist', $testFile);

            if (file_exists($renamedFile)) {
                rename($renamedFile, $testFile);
            }
        }
    }

    public static function tearDownAfterClass()
    {
        foreach (self::$testFiles as $testFile) {
            if (file_exists($testFile)) {
                rename($testFile, str_replace('.xml.dist', '.txml.dist', $testFile));
            }
        }
    }

    protected function setUp()
    {
        $this->currentCwd = getcwd();
        chdir(\dirname(__DIR__));
    }

    protected function tearDown()
    {
        chdir($this->currentCwd);
    }

    public function testInstall()
    {
        $cmd = 'bin/simple-phpunit install';
        $this->execute($cmd, $output, $exitCode);
        $this->assertSame(0, $exitCode);
    }

    public function testSimplePhpunitShortConfigurationFile()
    {
        $cmd = 'bin/simple-phpunit -c Tests/SimplePhpUnitTest/Modul1/phpunit.xml.dist';
        $this->execute($cmd, $output);
        $this->assertContains('OK (7 tests, 11 assertions)', implode(PHP_EOL, $output));
    }

    public function testSimplePhpunitWithConfigurationWithFilter()
    {
        $cmd = 'bin/simple-phpunit --filter=testEnv --configuration Tests/SimplePhpUnitTest/Modul1/phpunit.xml.dist';
        $this->execute($cmd, $output);
        $this->assertContains('OK (1 test, 1 assertion)', implode(PHP_EOL, $output));
    }

    public function testParallelTests()
    {
        $cmd = 'bin/simple-phpunit Tests/SimplePhpUnitTest';
        $this->execute($cmd, $output);

        // Check parallel test suites are runned successfully
        $testSuites = explode('Test Suite', implode(PHP_EOL, $output));

        unset($testSuites[0]); // Remove header output
        $testSuites = array_values($testSuites);
        $this->assertCount(2, $testSuites);

        $this->assertContains('OK (7 tests, 11 assertions)', $testSuites[0]);
        $this->assertContains('OK (7 tests, 11 assertions)', $testSuites[1]);

        // Check different phpunit versions are installed
        $this->assertFileExists(\dirname(__DIR__).'/.phpunit/phpunit-6.5-remove-symfony_yaml-phpspec_prophecy/phpunit');
        $this->assertFileExists(\dirname(__DIR__).'/.phpunit/phpunit-7.4-remove-phpspec_prophecy-symfony_yaml/phpunit');
    }

    private function execute($command, &$output = null, &$return_var = null)
    {
        $oldPhpUnitRootDirectory = getenv('SYMFONY_PHPUNIT_ROOT_DIRECTORY');
        $oldPhpUnitDirectory = getenv('SYMFONY_PHPUNIT_DIR');

        // Use putenv vor windows compatible setting of environment variables
        putenv('SYMFONY_PHPUNIT_ROOT_DIRECTORY='.\dirname(__DIR__));
        putenv('SYMFONY_PHPUNIT_DIR='.\dirname(__DIR__).'/.phpunit');

        $result = exec(
            sprintf('php %s', $command),
            $output,
            $return_var
        );

        // Reset env variables
        if (false !== $oldPhpUnitRootDirectory) {
            // Set to old value
            putenv('SYMFONY_PHPUNIT_ROOT_DIRECTORY='.$oldPhpUnitRootDirectory);
        } else {
            // Remove when no old value exists
            putenv('SYMFONY_PHPUNIT_ROOT_DIRECTORY');
        }

        if (false !== $oldPhpUnitDirectory) {
            // Set to old value
            putenv('SYMFONY_PHPUNIT_DIR='.$oldPhpUnitDirectory);
        } else {
            // Remove when no old value exists
            putenv('SYMFONY_PHPUNIT_DIR');
        }

        return $result;
    }
}
