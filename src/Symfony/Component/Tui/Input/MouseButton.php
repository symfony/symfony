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
 * The button reported by a mouse event.
 *
 * Wheel scrolling is reported as the dedicated WheelUp/WheelDown buttons
 * rather than as a regular button press.
 *
 * @experimental
 *
 * @author Louis-Arnaud Catoire <la.catoire@gmail.com>
 */
enum MouseButton
{
    case None;
    case Left;
    case Middle;
    case Right;
    case WheelUp;
    case WheelDown;
}
