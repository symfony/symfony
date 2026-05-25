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
 * Layout direction for container widgets.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
enum Direction: string
{
    case Vertical = 'vertical';
    case Horizontal = 'horizontal';
}
