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

        if (\defined('HHVM_VERSION')) {
            $this->markTestSkipped('This test cannot be run on HHVM.');
        }

        exec('type phpdbg 2> /dev/null', $output, $returnCode);

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

        exec("$php $phpunit -c $dir/phpunit-without-listener.xml.dist $dir/tests/ --coverage-text --colors=never 2> /dev/null", $output);
        $output = implode("\n", $output);
        $this->assertMatchesRegularExpression('/FooCov\n\s*Methods:\s+100.00%[^\n]+Lines:\s+100.00%/', $output);

        exec("$php $phpunit -c $dir/phpunit-with-listener.xml.dist $dir/tests/ --coverage-text --colors=never 2> /dev/null", $output);
        $output = implode("\n", $output);

        if (false === strpos($output, 'FooCov')) {
            $this->addToAssertionCount(1);
        } else {
            $this->assertMatchesRegularExpression('/FooCov\n\s*Methods:\s+0.00%[^\n]+Lines:\s+0.00%/', $output);
        }

        $this->assertStringContainsString("SutNotFoundTest::test\nCould not find the tested class.", $output);
        $this->assertStringNotContainsString("CoversTest::test\nCould not find the tested class.", $output);
        $this->assertStringNotContainsString("CoversDefaultClassTest::test\nCould not find the tested class.", $output);
        $this->assertStringNotContainsString("CoversNothingTest::test\nCould not find the tested class.", $output);
    }
}
