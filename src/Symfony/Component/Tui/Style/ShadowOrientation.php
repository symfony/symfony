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
 * Direction in which a drop-shadow is projected from a widget.
 *
 * The shadow is always offset by 1 cell relative to the widget edge,
 * creating a "lifted" depth effect. The orientation controls which
 * corner the shadow extends toward.
 *
 * @experimental
 *
 * @author Sylvain Blondeau <contact@sylvainblondeau.dev>
 */
enum ShadowOrientation
{
    case BottomRight;
    case BottomLeft;
    case TopRight;
    case TopLeft;

    public function isBottom(): bool
    {
        return self::BottomRight === $this || self::BottomLeft === $this;
    }

    public function isRight(): bool
    {
        return self::BottomRight === $this || self::TopRight === $this;
    }
}
