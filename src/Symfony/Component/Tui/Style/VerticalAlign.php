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
 * Vertical alignment of child widgets within a container.
 *
 * Controls how child widget content is positioned vertically when it
 * renders shorter than the container's available height.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
enum VerticalAlign: string
{
    case Top = 'top';
    case Center = 'center';
    case Bottom = 'bottom';
}
