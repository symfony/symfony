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
 * Vertical entrance direction for transitions.
 *
 * @experimental
 *
 * @author Simon André <smn.andre@gmail.com>
 */
enum VerticalDirection
{
    /** The incoming screen enters from the top edge. */
    case Top;

    /** The incoming screen enters from the bottom edge. */
    case Bottom;
}
