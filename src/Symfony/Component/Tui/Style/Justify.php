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
 * How the free columns of a horizontal row are shared between its children,
 * like the CSS justify-content distribution values.
 *
 * @experimental
 */
enum Justify: string
{
    /** The first child at the start, the last at the end, the free space between them */
    case SpaceBetween = 'space-between';
    /** Half a share before the first child and after the last, a share between children */
    case SpaceAround = 'space-around';
    /** Equal shares before, between and after the children */
    case SpaceEvenly = 'space-evenly';
}
