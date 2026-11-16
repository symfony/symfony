<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

/**
 * Terminal cursor shape, mapped to DECSCUSR escape sequences.
 *
 * These values correspond to the parameter N in `ESC [ N SP q`
 * (DECSCUSR: Set Cursor Style). Odd values produce a blinking
 * cursor; even values produce a steady one. The blinking variants
 * are the default, so the terminal provides native cursor animation
 * at zero CPU cost.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
enum CursorShape: int
{
    case Block = 1;
    case SteadyBlock = 2;
    case Underline = 3;
    case SteadyUnderline = 4;
    case Bar = 5;
    case SteadyBar = 6;

    /**
     * The same shape, not blinking, like caret-animation: manual.
     */
    public function steady(): self
    {
        return self::from($this->value + $this->value % 2);
    }
}
