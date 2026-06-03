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
 * Horizontal alignment of child widgets within a container.
 *
 * Controls how a child widget's block is positioned when it renders
 * narrower than the container's available width (e.g. due to maxColumns).
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
enum Align: string
{
    case Left = 'left';
    case Center = 'center';
    case Right = 'right';
}
