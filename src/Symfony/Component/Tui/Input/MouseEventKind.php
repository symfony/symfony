<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Input;

/**
 * The kind of interaction reported by a mouse event.
 *
 * @experimental
 *
 * @author Louis-Arnaud Catoire <la.catoire@gmail.com>
 */
enum MouseEventKind
{
    case Press;
    case Release;
    case Drag;
    case Move;
}
