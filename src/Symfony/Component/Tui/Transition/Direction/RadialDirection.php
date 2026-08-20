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
 * Radial reveal direction for transitions.
 *
 * @experimental
 *
 * @author Simon André <smn.andre@gmail.com>
 */
enum RadialDirection
{
    /** The reveal travels from the outer edge towards the center. */
    case Inward;

    /** The reveal travels from the center towards the outer edge. */
    case Outward;
}
