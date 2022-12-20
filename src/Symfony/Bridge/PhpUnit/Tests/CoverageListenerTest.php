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

class CoverageListenerTest extends TestCase
{
    public function test()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('This test cannot be run on Windows.');
        }

        exec('type phpdbg 2> /dev/null', $output, $returnCode);

        if (0 === $returnCode) {
            $php = 'phpdbg -qrr';
        } else {
            exec('php --ri xdebug -d zend_extension=xdebug.so 2> /dev/null', $output, $returnCode);
            if (0 !== $returnCode) {
                self::markTestSkipped('Xdebug is required to run this test.');
            }
            $php = 'php -d zend_extension=xdebug.so';
        }

        $dir = __DIR__.'/../Tests/Fixtures/coverage';
        $phpunit = $_SERVER['argv'][0];

        exec("$php $phpunit -c $dir/phpunit-without-listener.xml.dist $dir/tests/ --coverage-text --colors=never 2> /dev/null", $output);
        $output = implode("\n", $output);
        self::assertMatchesRegularExpression('/FooCov\n\s*Methods:\s+100.00%[^\n]+Lines:\s+100.00%/', $output);

        exec("$php $phpunit -c $dir/phpunit-with-listener.xml.dist $dir/tests/ --coverage-text --colors=never 2> /dev/null", $output);
        $output = implode("\n", $output);

        if (false === strpos($output, 'FooCov')) {
            self::addToAssertionCount(1);
        } else {
            self::assertMatchesRegularExpression('/FooCov\n\s*Methods:\s+0.00%[^\n]+Lines:\s+0.00%/', $output);
        }

        self::assertStringContainsString("SutNotFoundTest::test\nCould not find the tested class.", $output);
        self::assertStringNotContainsString("CoversTest::test\nCould not find the tested class.", $output);
        self::assertStringNotContainsString("CoversDefaultClassTest::test\nCould not find the tested class.", $output);
        self::assertStringNotContainsString("CoversNothingTest::test\nCould not find the tested class.", $output);
    }
}
