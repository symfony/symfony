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
enum PositionMode: string
{
    /** In the flow, the containing block of the absolute widgets inside */
    case Relative = 'relative';
    /** Out of the flow, at its insets from the closest positioned ancestor */
    case Absolute = 'absolute';
    /** Out of the flow, at its insets from the screen */
    case Fixed = 'fixed';
}
