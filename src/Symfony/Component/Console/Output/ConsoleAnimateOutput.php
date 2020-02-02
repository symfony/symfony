<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Output;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Helper\AnimateOutputEffect;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Terminal;

/**
 * @author Jibé Barth <barth.jib@gmail.com>
 */
class ConsoleAnimateOutput extends StreamOutput
{
    /**
     * Write instantly
     */
    public const NO_WRITE_ANIMATION = 0;
    /**
     * Write 1 char every 0.005 sec
     */
    public const WRITE_VERY_FAST = 1;
    /**
     * Write 1 char every 0.025 sec
     */
    public const WRITE_FAST = 5;
    /**
     * Write 1 char every 0.05 sec
     */
    public const WRITE_NORMAL = 10;
    /**
     * Write 1 char every 0.25 sec
     */
    public const WRITE_SLOW = 50;
    /**
     * Write 1 char every 0.5 sec
     */
    public const WRITE_VERY_SLOW = 100;
    /**
     * Write 1 char every second
     */
    public const WRITE_VERY_VERY_SLOW = 200;

    private const ANIMATE_LETTER_TIME = 5000;

    private $slowDown;
    private $currentEffect;
    private $terminal;

    public function __construct($stream, int $verbosity = self::VERBOSITY_NORMAL, bool $decorated = null, OutputFormatterInterface $formatter = null, int $slowDown = self::WRITE_NORMAL)
    {
        parent::__construct($stream, $verbosity, $decorated, $formatter);
        $this->terminal = new Terminal();
        $this->currentEffect = $this->progressiveWrite();
        $this->slowDown = $slowDown;
    }

    /**
     * Clear all terminal screen
     */
    public function clearScreen(): void
    {
        $this->clearLines($this->terminal->getHeight());
    }

    /**
     * Clears previous output for this console
     *
     * @param int $lines Number of lines to clear. If 0, then the current line is cleaned
     */
    public function clear(int $lines = 0): void
    {
        if (!$this->isDecorated()) {
            return;
        }

        $this->clearLines($lines);
    }

    public function wait(float $time = 1): void
    {
        usleep(1000000 * $time);
    }

    public function showCursor(): void
    {
        parent::doWrite("\033[?25h", false);
    }

    public function hideCursor(): void
    {
        parent::doWrite("\033[?25l", false);
    }

    public function setCustomEffect(\Closure $closure)
    {
        $this->currentEffect = $closure;
    }

    public function resetEffect(): void
    {
        $this->currentEffect = $this->progressiveWrite();
    }

    public function setSlowDown(int $slowDown = self::WRITE_NORMAL): void
    {
        $this->slowDown = $slowDown;
    }

    public function getSlowDown(): int
    {
        return $this->slowDown;
    }

    /**
     * Bypass animation
     */
    public function directWrite(string $message, bool $newLine = false): void
    {
        parent::doWrite($message, $newLine);
    }

    /**
     * Overwrites the previous output with a new message.
     *
     * @param array|string $message
     */
    public function overwriteln($message, int $linesToClear = 0): void
    {
        $this->clear($linesToClear);

        $this->writeln($message);
    }

    /**
     * Overwrites the current line with a new message.
     *
     * @param array|string $message
     */
    public function overwrite($message, int $linesToClear = 0): void
    {
        $this->clear($linesToClear);

        $this->write($message);
    }

    public function getUsleepDuration(): int
    {
        return self::ANIMATE_LETTER_TIME * $this->slowDown;
    }

    public function __destruct()
    {
        // Restore cursor in case it has been hidden
        $this->showCursor();
    }

    /**
     * {@inheritdoc}
     */
    protected function doWrite($message, $newline): void
    {
        if (!$this->isDecorated()) {
            parent::doWrite($message, $newline);

            return;
        }

        call_user_func($this->currentEffect, $message, $newline);
    }

    private function clearLines(int $numberOfLinesToClear = 0): void
    {
        // erase current line
        if ($numberOfLinesToClear === 0) {
            // add a new Line
            parent::doWrite(PHP_EOL, false);
            // jump cursor to beginning of previous line
            parent::doWrite("\033[0A", false);
            // erase the end of line
            parent::doWrite("\033[2K", false);
        }

        if ($numberOfLinesToClear > 0) {
            // move cursor up n lines at beginning
            parent::doWrite(sprintf("\x1b[%dA", $numberOfLinesToClear), false);
            // erase to end of screen
            parent::doWrite("\x1b[0J", false);
        }
    }

    private function progressiveWrite(): \Closure
    {
        return AnimateOutputEffect::progressive($this);

    }
}
