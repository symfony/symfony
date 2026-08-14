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
 * @experimental
 */
enum GradientDirection
{
    case LeftToRight;
    case RightToLeft;
    case TopToBottom;
    case BottomToTop;

    public function toAngle(): Angle
    {
        return match ($this) {
            self::LeftToRight => Angle::degrees(0),
            self::TopToBottom => Angle::degrees(90),
            self::RightToLeft => Angle::degrees(180),
            self::BottomToTop => Angle::degrees(270),
        };
    }
}
