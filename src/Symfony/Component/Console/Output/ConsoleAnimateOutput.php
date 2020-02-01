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

class ConsoleAnimateOutput extends StreamOutput
{
    public const NO_PROGRESSIVE = 0;
    public const PROGRESSIVE_VERY_QUICK = 1;
    public const PROGRESSIVE_QUICK = 5;
    public const PROGRESSIVE_NORMAL = 10;
    public const PROGRESSIVE_SLOW = 15;
    public const PROGRESSIVE_VERY_SLOW = 20;

    private const ANIMATE_LETTER_TIME = 5000;

    private $slowDown = 0;
    private $cursorVisible = true;
    private $currentEffect;
    private $terminal;

    public function __construct($stream, int $verbosity = self::VERBOSITY_NORMAL, bool $decorated = null, OutputFormatterInterface $formatter = null, int $slowDown = 0)
    {
        parent::__construct($stream, $verbosity, $decorated, $formatter);
        $this->terminal = new Terminal();
        $this->currentEffect = $this->progressiveWrite();
    }

    /**
     * Set progressive writing speed. (NO_PROGRESSIVE write instantanly)
     *
     * @param int $progressive
     */
    public function setProgressive(int $progressive = self::NO_PROGRESSIVE)
    {
        $this->currentEffect = $this->progressiveWrite();
        $this->slowDown = $progressive;
    }

    /**
     * Clear all terminal screen
     */
    public function clearScreen()
    {
        $this->clearLines($this->terminal->getHeight());
    }

    /**
     * Clears previous output for this console
     *
     * @param int $lines Number of lines to clear. If 0, then the current line is cleaned
     */
    public function clear(int $lines = 0)
    {
        if (!$this->isDecorated()) {
            return;
        }

        $this->clearLines($lines);
    }

    public function wait(float $time = 1)
    {
        usleep(1000000 * $time);
    }

    public function showCursor()
    {
        $this->cursorVisible = true;
        parent::doWrite("\033[?25h", false);
    }

    public function hideCursor()
    {
        $this->cursorVisible = false;
        parent::doWrite("\033[?25l", false);
    }

    public function setCustomEffect(\Closure $closure)
    {
        $this->currentEffect = $closure;
    }


    public function setSlowDown(int $slowDown = self::PROGRESSIVE_NORMAL)
    {
        $this->slowDown = $slowDown;
    }

    public function getSlowDown(): int
    {
        return $this->slowDown;
    }

    public function parentWrite(string $message, bool $newLine = false)
    {
        parent::doWrite($message, $newLine);
    }

    /**
     * Overwrites the previous output with a new message.
     *
     * @param array|string $message
     */
    public function overwriteln($message, ?int $slowDown = null, int $linesToClear = 0)
    {
        $this->clear($linesToClear);
        if (null !== $slowDown) {
            $this->setSlowDown($slowDown);
        }

        $this->writeln($message);
    }

    /**
     * Overwrites the current line with a new message.
     *
     * @param array|string $message
     */
    public function overwrite($message, ?int $slowDown = null, int $linesToClear = 0)
    {
        $this->clear($linesToClear);
        if (null !== $slowDown) {
            $this->setSlowDown($slowDown);
        }

        $this->write($message);
    }

    public function getUsleepDuration()
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
    protected function doWrite($message, $newline)
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
