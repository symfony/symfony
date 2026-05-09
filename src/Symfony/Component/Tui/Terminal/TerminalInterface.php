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

/**
 * Interface for terminal implementations.
 *
 * Provides abstraction over terminal I/O for the TUI framework.
 * Implementations handle raw mode, input reading, and output writing.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface TerminalInterface
{
    /**
     * Start the terminal with input and resize handlers.
     *
     * This typically enables raw mode, sets up signal handlers,
     * and prepares the terminal for TUI operation.
     *
     * @param callable(string): void $onInput                  Called when input is received
     * @param callable(): void       $onResize                 Called when terminal is resized
     * @param callable(): void       $onKittyProtocolActivated Called when Kitty keyboard protocol is detected
     */
    public function start(callable $onInput, callable $onResize, callable $onKittyProtocolActivated): void;

    /**
     * Stop the terminal and restore original state.
     *
     * This should restore the terminal to its original mode,
     * remove signal handlers, and clean up resources.
     */
    public function stop(): void;

    /**
     * Write data to the terminal output.
     */
    public function write(string $data): void;

    /**
     * Get the terminal width in columns.
     */
    public function getColumns(): int;

    /**
     * Get the terminal height in rows.
     */
    public function getRows(): int;

    /**
     * Check if Kitty keyboard protocol is active.
     *
     * The Kitty protocol provides enhanced key reporting including
     * key release events and modifier disambiguation.
     */
    public function isKittyProtocolActive(): bool;

    /**
     * Move cursor up (negative) or down (positive) by N lines.
     */
    public function moveBy(int $lines): void;

    /**
     * Hide the terminal cursor.
     */
    public function hideCursor(): void;

    /**
     * Show the terminal cursor.
     */
    public function showCursor(): void;

    /**
     * Clear the current line.
     */
    public function clearLine(): void;

    /**
     * Clear from cursor to end of screen.
     */
    public function clearFromCursor(): void;

    /**
     * Clear entire screen and move cursor to home position.
     */
    public function clearScreen(): void;

    /**
     * Set the terminal window title.
     */
    public function setTitle(string $title): void;

    /**
     * Ring the terminal bell.
     *
     * Emits the BEL character (\x07) which causes the terminal
     * to produce an audible or visual notification.
     */
    public function bell(): void;

    /**
     * Whether this is a virtual (non-TTY) terminal.
     */
    public function isVirtual(): bool;
}
