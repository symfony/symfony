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

    private $progressive = false;
    private $slowDown = 0;
    private $cursorVisible = true;

    private $terminal;

    public function __construct($stream, int $verbosity = self::VERBOSITY_NORMAL, bool $decorated = null, OutputFormatterInterface $formatter = null, int $slowDown = 0)
    {
        parent::__construct($stream, $verbosity, $decorated, $formatter);
        $this->terminal = new Terminal();
    }

    /**
     * Set progressive writing speed. (NO_PROGRESSIVE write instantanly)
     *
     * @param int $progressive
     */
    public function setProgressive(int $progressive = self::NO_PROGRESSIVE)
    {
        $this->progressive = true;
        if ($progressive === self::NO_PROGRESSIVE) {
            $this->progressive = false;
        }

        $this->slowDown = $progressive;
    }

    public function clearScreen()
    {
        $this->clearLines($this->terminal->getHeight());
    }

    /**
     * Clears previous output for this console
     *
     * @param int $lines Number of lines to clear. If null, then the entire output of this section is cleared
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

    /**
     * Overwrites the previous output with a new message.
     *
     * @param array|string $message
     */
    public function overwriteln($message, ?int $progressive = null, int $linesToClear = 0)
    {
        $this->clear($linesToClear);
        if (null !== $progressive) {
            $this->setProgressive($progressive);
        }

        $this->writeln($message);
    }

    /**
     * Overwrites the current line with a new message.
     *
     * @param array|string $message
     */
    public function overwrite($message, ?int $progressive = null, int $linesToClear = 0)
    {
        $this->clear($linesToClear);
        if (null !== $progressive) {
            $this->setProgressive($progressive);
        }

        $this->write($message);
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

        foreach (str_split($message) as $char) {
            parent::doWrite($char, false);
            usleep(self::ANIMATE_LETTER_TIME * $this->slowDown);
        }

        if ($newline) {
            parent::doWrite('', $newline);
        }
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
}
