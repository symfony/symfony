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

/**
 * Enumeration of terminal control escape sequences.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class EscapeSequences
{
    /**
     * Erases the entire current line.
     *
     * @var string
     */
    const LINE_ERASE = "\033[2K";

    /**
     * Shows the cursor.
     *
     * @var string
     */
    const CURSOR_SHOW = "\033[?25h";

    /**
     * Hides the cursor.
     *
     * @var string
     */
    const CURSOR_HIDE = "\033[?25l";

    /**
     * Moves the cursor backward N columns.
     *
     * @var string
     */
    const CURSOR_MOVE_BACKWARD_N = "\033[%dD";

    /**
     * Moves the cursor up N lines.
     *
     * @var string
     */
    const CURSOR_MOVE_UP_N = "\033[%dA";

    /**
     * Moves the cursor down N lines.
     *
     * @var string
     */
    const CURSOR_MOVE_DOWN_N = "\033[%dB";
}
