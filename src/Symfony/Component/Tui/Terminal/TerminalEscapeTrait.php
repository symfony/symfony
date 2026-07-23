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
 * Shares the escape sequences emitted by terminal implementations.
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
trait TerminalEscapeTrait
{
    abstract public function write(string $data): void;

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
        // Strip C0/C1 control characters so an attacker-controlled title
        // cannot break out of the OSC sequence or inject further escapes.
        $safe = preg_replace("/[\x00-\x1f\x7f]|\xc2[\x80-\x9f]/", '', $title);

        $this->write("\x1b]0;{$safe}\x07");
    }
}
