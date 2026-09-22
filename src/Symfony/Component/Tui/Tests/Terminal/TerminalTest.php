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

    /**
     * A pseudo-terminal whose window size was never set makes "stty size"
     * report "0 0". A zero size means the size is unknown, not that the
     * terminal is zero cells wide.
     */
    public function testZeroDimensionsFallBackToTheDefaultSize()
    {
        $terminal = new Terminal();
        $columns = new \ReflectionProperty($terminal, 'cachedColumns');
        $rows = new \ReflectionProperty($terminal, 'cachedRows');

        $columns->setValue($terminal, 0);
        $rows->setValue($terminal, 0);

        $this->assertSame(80, $terminal->getColumns());
        $this->assertSame(24, $terminal->getRows());

        // Only zero is special-cased: a reported size is returned as is
        $columns->setValue($terminal, 120);
        $rows->setValue($terminal, 40);

        $this->assertSame(120, $terminal->getColumns());
        $this->assertSame(40, $terminal->getRows());
    }

    public function testDimensionsComeFromThePseudoTerminal()
    {
        if (!\function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is required.');
        }

        $descriptors = [0 => ['pty'], 1 => ['pty'], 2 => ['pipe', 'w'], 3 => ['pipe', 'r']];
        $process = @proc_open([\PHP_BINARY, __DIR__.'/../Fixtures/terminal_dimensions.php'], $descriptors, $pipes);
        if (!\is_resource($process)) {
            $this->markTestSkipped('Cannot allocate a pseudo terminal.');
        }

        try {
            $this->stty($pipes[1], ['cols', '113', 'rows', '37']);
            fwrite($pipes[3], "go\n");
            stream_set_blocking($pipes[1], false);

            $output = '';
            $deadline = microtime(true) + 5;
            do {
                $output .= $this->readPty($pipes[1]);
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);

            if ($status['running']) {
                proc_terminate($process, 9);
                $this->fail('Terminal child did not finish: '.$output);
            }
            $output .= $this->readPty($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            $this->assertSame(0, $status['exitcode'], $output.$error);

            $this->assertSame([113, 37], json_decode($output, true, flags: \JSON_THROW_ON_ERROR));
        } finally {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }

    private function readPty($stream): string
    {
        // Linux reports EIO when the last PTY slave closes.
        set_error_handler(static fn ($severity, $message) => str_contains($message, 'errno=5 '));
        try {
            return (string) stream_get_contents($stream);
        } finally {
            restore_error_handler();
        }
    }

    private function stty($stream, array $arguments): void
    {
        $process = proc_open(['stty', ...$arguments], [0 => $stream, 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $error);
    }
}
