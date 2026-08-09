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
}
