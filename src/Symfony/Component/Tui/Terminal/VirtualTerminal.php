<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Terminal;

use Symfony\Component\Tui\Input\StdinBuffer;

/**
 * Virtual terminal for testing.
 *
 * Captures output and allows simulating input for unit tests.
 * Uses StdinBuffer for input parsing to match real Terminal behavior.
 *
 * Virtual terminals don't have a physical screen: they don't scroll
 * content out of the addressable area and don't respond to terminal
 * queries (e.g. cell size). This is used by the rendering pipeline
 * to adjust its behavior accordingly.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class VirtualTerminal implements TerminalInterface
{
    private string $output = '';
    private ?StdinBuffer $stdinBuffer = null;
    private ?\Closure $onResize = null;

    public function __construct(
        private int $columns = 80,
        private int $rows = 24,
        private bool $kittyProtocolActive = false,
    ) {
    }

    public function start(callable $onInput, callable $onResize, callable $onKittyProtocolActivated): void
    {
        $this->onResize = $onResize(...);

        // Set up StdinBuffer for input parsing (matches real Terminal behavior)
        $this->stdinBuffer = new StdinBuffer();
        $this->stdinBuffer->onData($onInput);

        // Re-wrap paste content with bracketed paste markers (matches real Terminal behavior)
        $this->stdinBuffer->onPaste(static function (string $content) use ($onInput): void {
            $onInput("\x1b[200~".$content."\x1b[201~");
        });
    }

    public function stop(): void
    {
        $this->onResize = null;
        $this->stdinBuffer = null;
    }

    public function write(string $data): void
    {
        $this->output .= $data;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    public function getRows(): int
    {
        return $this->rows;
    }

    public function isKittyProtocolActive(): bool
    {
        return $this->kittyProtocolActive;
    }

    public function moveBy(int $lines): void
    {
        if ($lines > 0) {
            $this->write("\x1b[{$lines}B");
        } elseif ($lines < 0) {
            $this->write("\x1b[".(-$lines).'A');
        }
    }

    public function hideCursor(): void
    {
        $this->write("\x1b[?25l");
    }

    public function showCursor(): void
    {
        $this->write("\x1b[?25h");
    }

    public function clearLine(): void
    {
        $this->write("\x1b[2K");
    }

    public function clearFromCursor(): void
    {
        $this->write("\x1b[0J");
    }

    public function clearScreen(): void
    {
        $this->write("\x1b[2J\x1b[H");
    }

    public function setTitle(string $title): void
    {
        $safe = preg_replace("/[\x00-\x1f\x7f]|\xc2[\x80-\x9f]/", '', $title);
        $this->write("\x1b]0;{$safe}\x07");
    }

    public function bell(): void
    {
        $this->write("\x07");
    }

    public function isVirtual(): bool
    {
        return true;
    }

    // Testing helpers

    public function getOutput(): string
    {
        return $this->output;
    }

    public function clearOutput(): void
    {
        $this->output = '';
    }

    /**
     * Get all output written since the last call and clear the buffer.
     *
     * This is useful for streaming scenarios where you need to get
     * the output diff since the last read (e.g. publishing to Mercure).
     */
    public function consumeOutput(): string
    {
        $output = $this->output;
        $this->output = '';

        return $output;
    }

    /**
     * Simulate raw input from the user.
     *
     * Input is processed through StdinBuffer for proper sequence parsing,
     * matching the behavior of the real Terminal.
     */
    public function simulateInput(string $data): void
    {
        if (null !== $this->stdinBuffer) {
            $this->stdinBuffer->process($data);
            $this->stdinBuffer?->flush();
        }
    }

    public function simulateResize(int $columns, int $rows): void
    {
        $this->columns = $columns;
        $this->rows = $rows;

        if (null !== $this->onResize) {
            ($this->onResize)();
        }
    }

    public function setKittyProtocolActive(bool $active): void
    {
        $this->kittyProtocolActive = $active;
    }
}
