<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Render;

/**
 * A compositing layer: content lines at a position, optionally transparent.
 *
 * When transparent, cells with no explicit background preserve the
 * background from the layer below. Fully unstyled spaces are completely
 * transparent (the entire cell below shows through).
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Layer
{
    /**
     * @param string[] $lines       ANSI-formatted content lines
     * @param int      $row         Vertical offset in the composite
     * @param int      $col         Horizontal offset in the composite
     * @param bool     $transparent Whether cells with default background inherit from layers below
     * @param int|null $width       Explicit canvas width (used by the base layer to define the canvas size)
     * @param int|null $height      Explicit canvas height (used by the base layer to define the canvas size)
     */
    public function __construct(
        public readonly array $lines,
        public readonly int $row = 0,
        public readonly int $col = 0,
        public readonly bool $transparent = false,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
    ) {
    }
}
