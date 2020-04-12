<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Pierre du Plessis <pdples@gmail.com>
 */
class Cursor
{
    private $output;

    private $input;

    public function __construct(OutputInterface $output, $input = STDIN)
    {
        $this->output = $output;
        $this->input = $input;
    }

    public function moveUp(int $lines = 1)
    {
        $this->output->write(sprintf("\x1b[%dA", $lines));
    }

    public function moveDown(int $lines = 1)
    {
        $this->output->write(sprintf("\x1b[%dB", $lines));
    }

    public function moveRight(int $columns = 1)
    {
        $this->output->write(sprintf("\x1b[%dC", $columns));
    }

    public function moveLeft(int $columns = 1)
    {
        $this->output->write(sprintf("\x1b[%dD", $columns));
    }

    public function moveToColumn(int $column)
    {
        $this->output->write(sprintf("\x1b[%dG", $column));
    }

    public function moveToPosition(int $column, int $row)
    {
        $this->output->write(sprintf("\x1b[%d;%dH", $row + 1, $column));
    }

    public function savePosition()
    {
        $this->output->write("\x1b7");
    }

    public function restorePosition()
    {
        $this->output->write("\x1b8");
    }

    public function hide()
    {
        $this->output->write("\x1b[?25l");
    }

    public function show()
    {
        $this->output->write("\x1b[?25h\x1b[?0c");
    }

    /**
     * Clears all the output from the current line.
     */
    public function clearLine()
    {
        $this->output->write("\x1b[2K");
    }

    /**
     * Clears all the output from the current line after the current position.
     */
    public function clearLineAfter()
    {
        $this->output->write("\x1b[K");
    }

    /**
     * Clears all the output from the cursors' current position to the end of the screen.
     */
    public function clearOutput()
    {
        $this->output->write("\x1b[0J");
    }

    /**
     * Clears the entire screen.
     */
    public function clearScreen()
    {
        $this->output->write("\x1b[2J");
    }

    /**
     * Returns the current cursor position as x,y coordinates.
     */
    public function getCurrentPosition(): array
    {
        static $isTtySupported;

        if (null === $isTtySupported && \function_exists('proc_open')) {
            $isTtySupported = (bool) @proc_open('echo 1 >/dev/null', [['file', '/dev/tty', 'r'], ['file', '/dev/tty', 'w'], ['file', '/dev/tty', 'w']], $pipes);
        }

        if (!$isTtySupported) {
            return [1, 1];
        }

        $sttyMode = shell_exec('stty -g');
        shell_exec('stty -icanon -echo');

        @fwrite($this->input, "\033[6n");

        $code = trim(fread($this->input, 1024));

        shell_exec(sprintf('stty %s', $sttyMode));

        sscanf($code, "\033[%d;%dR", $row, $col);

        return [$col, $row];
    }
}
