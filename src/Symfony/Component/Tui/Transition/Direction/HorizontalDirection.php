<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Transition\Direction;

/**
 * Horizontal entrance direction for transitions.
 *
 * @experimental
 *
 * @author Simon André <smn.andre@gmail.com>
 */
enum HorizontalDirection
{
    /** The incoming screen enters from the left edge. */
    case Left;

    /** The incoming screen enters from the right edge. */
    case Right;
}
