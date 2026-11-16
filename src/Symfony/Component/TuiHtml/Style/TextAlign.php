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
 * Text alignment within a widget's content area.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
enum TextAlign: string
{
    case Left = 'left';
    case Center = 'center';
    case Right = 'right';
    /** Like Left, but the text widgets that wrap stretch the spaces of every line but the last of a paragraph */
    case Justify = 'justify';
}
