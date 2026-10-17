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

class SymfonyTestsListenerTest extends TestCase
{
    /**
     * @dataProvider provideSkippedTests
     */
    public function testStderrDoesNotCorruptJunitOutput(bool $skipTests)
    {
        $skippedFile = tempnam(sys_get_temp_dir(), 'skipped');
        file_put_contents($skippedFile, '<?php return [];');
        $env = getenv();
        unset($env['SYMFONY_PHPUNIT_SKIPPED_TESTS']);
        if ($skipTests) {
            $env['SYMFONY_PHPUNIT_SKIPPED_TESTS'] = $skippedFile;
        }

        $command = [\PHP_BINARY, '-d', 'memory_limit=512M', $_SERVER['argv'][0], '--stderr', '--colors=never', '--log-junit', 'php://stdout', __DIR__.'/FailTests/NoAssertionsTestNotRisky.php'];
        $process = proc_open(implode(' ', array_map('escapeshellarg', $command)), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        try {
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $errors);
        } finally {
            unlink($skippedFile);
        }

        $this->assertStringStartsWith('<?xml', ltrim($output));
        $this->assertStringContainsString('Testing ', $errors);
        if ($skipTests) {
            $this->assertStringContainsString('All tests already ran successfully.', $errors);
        }
    }

    public static function provideSkippedTests(): iterable
    {
        yield [false];
        yield [true];
    }
}
