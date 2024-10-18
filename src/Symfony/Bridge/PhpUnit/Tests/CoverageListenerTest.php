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

/**
 * @requires PHPUnit < 10
 */
class CoverageListenerTest extends TestCase
{
    public function test()
    {
        $dir = __DIR__.'/../Tests/Fixtures/coverage';
        $phpunit = $_SERVER['argv'][0];

        $php = $this->findCoverageDriver();

        $output = '';
        exec("$php $phpunit -c $dir/phpunit-without-listener.xml.dist $dir/tests/ --coverage-text --colors=never 2> /dev/null", $output);
        $output = implode("\n", $output);
        $this->assertMatchesRegularExpression('/FooCov\n\s*Methods:\s+100.00%[^\n]+Lines:\s+100.00%/', $output);

        $output = '';
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

    private function findCoverageDriver(): string
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test cannot be run on Windows.');
        }

        exec('php --ri xdebug -d zend_extension=xdebug 2> /dev/null', $output, $returnCode);
        if (0 === $returnCode) {
            return 'php -d zend_extension=xdebug';
        }

        exec('php --ri pcov -d zend_extension=pcov 2> /dev/null', $output, $returnCode);
        if (0 === $returnCode) {
            return 'php -d zend_extension=pcov';
        }

        exec('type phpdbg 2> /dev/null', $output, $returnCode);
        if (0 === $returnCode) {
            return 'phpdbg -qrr';
        }

        $this->markTestSkipped('Xdebug or pvoc is required to run this test.');
    }
}
