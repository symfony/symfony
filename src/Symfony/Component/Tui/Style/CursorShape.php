<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Style;

/**
 * Terminal cursor shape, mapped to DECSCUSR escape sequences.
 *
 * These values correspond to the parameter N in `ESC [ N SP q`
 * (DECSCUSR: Set Cursor Style). Odd values produce a blinking
 * cursor; even values produce a steady one. We use the blinking
 * variants so the terminal provides native cursor animation at
 * zero CPU cost.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
enum CursorShape: int
{
    case Block = 1;
    case Underline = 3;
    case Bar = 5;
}
