<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Render;

/**
 * Writes the frames a renderer produces to the terminal.
 *
 * A frame writer is created by {@see RendererInterface::createFrameWriter()}
 * and owns everything it writes to the terminal between two frames.
 *
 * @experimental
 */
interface FrameWriterInterface
{
    /**
     * Writes a frame to the terminal.
     *
     * Implementations may write only what changed since the previous frame,
     * and must position the terminal cursor so that getState() reports it.
     */
    public function writeFrame(LineBufferInterface $lines): void;

    /**
     * Forgets what was written, so that the next frame is written in full.
     *
     * The scroll offset is kept.
     */
    public function reset(): void;

    /**
     * Restores what the writer changed in the terminal besides the frames
     * (like cursor colors), when the TUI stops or the writer is replaced.
     */
    public function close(): void;

    /**
     * Sets the number of lines the viewport is moved up from the bottom of
     * the frame, when the frame is taller than the terminal.
     */
    public function setScrollOffset(int $offset): void;

    /**
     * Returns the number of lines written by the last frame and the row of
     * the terminal cursor in them, used to leave the cursor below the frame
     * when the TUI stops.
     *
     * @return array{cursor_row: int, line_count: int}
     */
    public function getState(): array;

    /**
     * Called when only the terminal height changed while the terminal kept
     * its content, moving rows between the screen and its scrollback (like
     * Termux when its software keyboard shows or hides).
     *
     * Implementations follow the rows the terminal moved so that the next
     * frame writes only what changed, or call reset() when they cannot.
     */
    public function onTerminalHeightChange(int $previousRows, int $rows): void;
}
