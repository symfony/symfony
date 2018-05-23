<?php

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\TestCase;

class CoverageListenerTest extends TestCase
{
    public function test()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test cannot be run on Windows.');
        }

        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('This test cannot be run on HHVM.');
        }

        exec('type phpdbg', $output, $returnCode);

        if (\PHP_VERSION_ID >= 70000 && 0 === $returnCode) {
            $php = 'phpdbg -qrr';
        } else {
            exec('php --ri xdebug -d zend_extension=xdebug.so 2> /dev/null', $output, $returnCode);
            if (0 !== $returnCode) {
                $this->markTestSkipped('Xdebug is required to run this test.');
            }
            $php = 'php -d zend_extension=xdebug.so';
        }

        $dir = __DIR__.'/../Tests/Fixtures/coverage';
        $phpunit = $_SERVER['argv'][0];

        exec("$php $phpunit -c $dir/phpunit-without-listener.xml.dist $dir/tests/ --coverage-text 2> /dev/null", $output);
        $output = implode("\n", $output);
        $this->assertContains('FooCov', $output);

        exec("$php $phpunit -c $dir/phpunit-with-listener.xml.dist $dir/tests/ --coverage-text 2> /dev/null", $output);
        $output = implode("\n", $output);
        $this->assertNotContains('FooCov', $output);
        $this->assertContains("SutNotFoundTest::test\nCould not find the tested class.", $output);
        $this->assertNotContains("CoversTest::test\nCould not find the tested class.", $output);
        $this->assertNotContains("CoversDefaultClassTest::test\nCould not find the tested class.", $output);
        $this->assertNotContains("CoversNothingTest::test\nCould not find the tested class.", $output);
    }
}
