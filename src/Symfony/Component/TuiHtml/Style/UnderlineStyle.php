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
 * The shape of an underline, like CSS text-decoration-style.
 *
 * Terminals that know the styled underlines (kitty, WezTerm, foot, iTerm2,
 * VTE, Windows Terminal...) draw them; the others draw a plain underline.
 *
 * @experimental
 */
enum UnderlineStyle: int
{
    case Single = 1;
    case Double = 2;
    case Curly = 3;
    case Dotted = 4;
    case Dashed = 5;
}
