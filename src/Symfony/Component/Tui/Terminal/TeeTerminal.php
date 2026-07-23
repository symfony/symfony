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
 * Terminal that delegates to multiple terminals simultaneously.
 *
 * This is useful for:
 * - Running examples with both a real terminal and a VirtualTerminal for testing
 * - Recording terminal output while displaying it
 * - Debugging terminal output
 *
 * The primary terminal is used for input handling and dimension queries.
 * All terminals receive write operations.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class TeeTerminal implements TerminalInterface
{
    /**
     * @param TerminalInterface $primary   The primary terminal (used for input and dimensions)
     * @param TerminalInterface $secondary Additional terminal that receives writes
     */
    public function __construct(
        private TerminalInterface $primary,
        private TerminalInterface $secondary,
    ) {
    }

    public function start(callable $onInput, callable $onResize, callable $onKittyProtocolActivated): void
    {
        // Start primary with real callbacks
        $this->primary->start($onInput, $onResize, $onKittyProtocolActivated);

        // Start secondary with no-op callbacks (they just record)
        $noopInput = static function (string $data): void {};
        $noopResize = static function (): void {};
        $noopKitty = static function (): void {};

        $this->secondary->start($noopInput, $noopResize, $noopKitty);
    }

    public function stop(): void
    {
        $this->primary->stop();
        $this->secondary->stop();
    }

    public function write(string $data): void
    {
        $this->primary->write($data);
        $this->secondary->write($data);
    }

    public function getColumns(): int
    {
        return $this->primary->getColumns();
    }

    public function getRows(): int
    {
        return $this->primary->getRows();
    }

    public function isKittyProtocolActive(): bool
    {
        return $this->primary->isKittyProtocolActive();
    }

    public function moveBy(int $lines): void
    {
        $this->primary->moveBy($lines);
        $this->secondary->moveBy($lines);
    }

    public function hideCursor(): void
    {
        $this->primary->hideCursor();
        $this->secondary->hideCursor();
    }

    public function showCursor(): void
    {
        $this->primary->showCursor();
        $this->secondary->showCursor();
    }

    public function clearLine(): void
    {
        $this->primary->clearLine();
        $this->secondary->clearLine();
    }

    public function clearFromCursor(): void
    {
        $this->primary->clearFromCursor();
        $this->secondary->clearFromCursor();
    }

    public function clearScreen(): void
    {
        $this->primary->clearScreen();
        $this->secondary->clearScreen();
    }

    public function setTitle(string $title): void
    {
        $this->primary->setTitle($title);
        $this->secondary->setTitle($title);
    }

    public function bell(): void
    {
        $this->primary->bell();
        $this->secondary->bell();
    }

    public function isVirtual(): bool
    {
        return $this->primary->isVirtual();
    }
}
