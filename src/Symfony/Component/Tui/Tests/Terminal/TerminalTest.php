<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Terminal;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\Terminal;

class TerminalTest extends TestCase
{
    protected function setUp(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('fireAndForget uses Unix shell syntax and is only invoked on macOS.');
        }
    }

    public function testFireAndForgetDoesNotBlock()
    {
        $terminal = new Terminal();
        $method = new \ReflectionMethod($terminal, 'fireAndForget');

        $start = microtime(true);
        $method->invoke($terminal, ['sleep', '10']);
        $elapsed = microtime(true) - $start;

        // Should return nearly instantly, not wait 10 seconds
        $this->assertLessThan(1.0, $elapsed);
    }

    public function testFireAndForgetProcessSurvivesCallerScope()
    {
        $marker = tempnam(sys_get_temp_dir(), 'faf_');
        unlink($marker);

        $terminal = new Terminal();
        $method = new \ReflectionMethod($terminal, 'fireAndForget');

        // Start a background command that creates a marker file after a short delay
        $method->invoke($terminal, ['sh', '-c', \sprintf('sleep 1 && touch %s', escapeshellarg($marker))]);

        // The process must still be running after fireAndForget returns
        $this->assertFileDoesNotExist($marker);

        // Wait for the background process to finish
        $deadline = microtime(true) + 5;
        while (!file_exists($marker) && microtime(true) < $deadline) {
            usleep(100_000);
        }

        $this->assertFileExists($marker);
        @unlink($marker);
    }
}
