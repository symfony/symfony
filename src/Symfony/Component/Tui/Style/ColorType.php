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
 * Represents the type of a terminal color.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
enum ColorType
{
    /** Basic 16 ANSI colors (named: 'black', 'red', 'green', etc.) */
    case Named;

    /** 256-color palette (integers 0-255) */
    case Palette;

    /** True color RGB (hex strings like '#ff5500' or '#f50') */
    case Hex;
}
