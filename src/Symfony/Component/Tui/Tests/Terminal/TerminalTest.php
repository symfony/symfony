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
use Revolt\EventLoop;
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

    public function testAnEscapeSequenceThatNeverCompletesStopsHoldingInputBack()
    {
        [$terminal, $processInput, $received] = $this->startInputProcessing();

        // A legacy terminal sends Alt+] as ESC ], which opens an OSC sequence
        $processInput->invoke($terminal, "\x1b]");
        $processInput->invoke($terminal, "abc\x03");

        $this->assertSame([], $received->getArrayCopy());

        EventLoop::run();

        $this->assertSame(["\x1b]", 'a', 'b', 'c', "\x03"], $received->getArrayCopy());
    }

    public function testAnEscapeSequenceSplitAcrossReadsIsNotTakenForTheEscapeKey()
    {
        [$terminal, $processInput, $received] = $this->startInputProcessing();

        $processInput->invoke($terminal, "\x1b");
        $processInput->invoke($terminal, '[A');

        $this->assertSame(["\x1b[A"], $received->getArrayCopy());
    }

    public function testALoneEscapeIsTheEscapeKeyOnceNothingFollows()
    {
        [$terminal, $processInput, $received] = $this->startInputProcessing();

        $processInput->invoke($terminal, "\x1b");

        $this->assertSame([], $received->getArrayCopy());

        EventLoop::run();

        $this->assertSame(["\x1b"], $received->getArrayCopy());
    }

    public function testALoneEscapeWaitsLongerOverSsh()
    {
        $terminal = new Terminal();
        $getEscapeTimeout = new \ReflectionMethod($terminal, 'getEscapeTimeout');
        $sshConnection = getenv('SSH_CONNECTION');
        $sshTty = getenv('SSH_TTY');

        try {
            putenv('SSH_CONNECTION');
            putenv('SSH_TTY');
            $this->assertSame(0.01, $getEscapeTimeout->invoke($terminal));

            putenv('SSH_CONNECTION=192.0.2.1 50000 192.0.2.2 22');
            $this->assertSame(0.1, $getEscapeTimeout->invoke($terminal));

            putenv('SSH_CONNECTION');
            putenv('SSH_TTY=/dev/ttys001');
            $this->assertSame(0.1, $getEscapeTimeout->invoke($terminal));
        } finally {
            putenv(false === $sshConnection ? 'SSH_CONNECTION' : 'SSH_CONNECTION='.$sshConnection);
            putenv(false === $sshTty ? 'SSH_TTY' : 'SSH_TTY='.$sshTty);
        }
    }

    /**
     * @return array{Terminal, \ReflectionMethod, \ArrayObject<int, string>}
     */
    private function startInputProcessing(): array
    {
        $terminal = new Terminal();
        $received = new \ArrayObject();
        (new \ReflectionProperty($terminal, 'onInput'))->setValue($terminal, static function (string $data) use ($received) { $received[] = $data; });
        (new \ReflectionProperty($terminal, 'started'))->setValue($terminal, true);
        (new \ReflectionMethod($terminal, 'setupStdinBuffer'))->invoke($terminal);

        return [$terminal, new \ReflectionMethod($terminal, 'processInput'), $received];
    }
}
