<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\CI;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\CI\GithubActionReporter;
use Symfony\Component\Console\Output\BufferedOutput;

class GithubActionReporterTest extends TestCase
{
    public function testIsGithubActionEnvironment()
    {
        $prev = getenv('GITHUB_ACTIONS');
        putenv('GITHUB_ACTIONS');

        try {
            self::assertFalse(GithubActionReporter::isGithubActionEnvironment());
            putenv('GITHUB_ACTIONS=1');
            self::assertTrue(GithubActionReporter::isGithubActionEnvironment());
        } finally {
            putenv('GITHUB_ACTIONS'.($prev ? "=$prev" : ''));
        }
    }

    /**
     * @dataProvider annotationsFormatProvider
     */
    public function testAnnotationsFormat(string $type, string $message, string $file = null, int $line = null, int $col = null, string $expected)
    {
        $reporter = new GithubActionReporter($buffer = new BufferedOutput());

        $reporter->{$type}($message, $file, $line, $col);

        self::assertSame($expected.\PHP_EOL, $buffer->fetch());
    }

    public function annotationsFormatProvider(): iterable
    {
        yield 'warning' => ['warning', 'A warning', null, null, null, '::warning::A warning'];
        yield 'error' => ['error', 'An error', null, null, null, '::error::An error'];
        yield 'debug' => ['debug', 'A debug log', null, null, null, '::debug::A debug log'];

        yield 'with message to escape' => [
            'debug',
            "There are 100% chances\nfor this to be escaped properly\rRight?",
            null,
            null,
            null,
            '::debug::There are 100%25 chances%0Afor this to be escaped properly%0DRight?',
        ];

        yield 'with meta' => [
            'warning',
            'A warning',
            'foo/bar.php',
            2,
            4,
            '::warning file=foo/bar.php,line=2,col=4::A warning',
        ];

        yield 'with file property to escape' => [
            'warning',
            'A warning',
            'foo,bar:baz%quz.php',
            2,
            4,
            '::warning file=foo%2Cbar%3Abaz%25quz.php,line=2,col=4::A warning',
        ];

        yield 'without file ignores col & line' => ['warning', 'A warning', null, 2, 4, '::warning::A warning'];
    }
}
