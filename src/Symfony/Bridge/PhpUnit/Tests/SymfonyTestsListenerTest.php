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
    public function testStderrDoesNotCorruptJunitOutput()
    {
        [$output, $errors] = $this->runPhpunitWithStderr();

        $this->assertStringStartsWith('<?xml', $output);
        $this->assertStringContainsString('Testing Symfony\Bridge\PhpUnit\Tests\FailTests\NoAssertionsTestNotRisky', $errors);
    }

    public function testStderrDoesNotCorruptJunitOutputWhenAllTestsAlreadyRan()
    {
        $skippedFile = tempnam(sys_get_temp_dir(), 'skipped');
        file_put_contents($skippedFile, '<?php return [];');

        try {
            [$output, $errors] = $this->runPhpunitWithStderr($skippedFile);
        } finally {
            unlink($skippedFile);
        }

        $this->assertStringStartsWith('<?xml', $output);
        $this->assertStringContainsString('All tests already ran successfully.', $errors);
    }

    private function runPhpunitWithStderr(?string $skippedFile = null): array
    {
        $env = getenv();
        unset($env['SYMFONY_PHPUNIT_SKIPPED_TESTS']);
        if (null !== $skippedFile) {
            $env['SYMFONY_PHPUNIT_SKIPPED_TESTS'] = $skippedFile;
        }

        $command = [\PHP_BINARY, $_SERVER['argv'][0], '--stderr', '--colors=never', '--log-junit', 'php://stdout', __DIR__.'/FailTests/NoAssertionsTestNotRisky.php'];
        $process = proc_open(implode(' ', array_map('escapeshellarg', $command)), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $errors);

        return [$output, $errors];
    }
}
