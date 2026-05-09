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

use Symfony\Component\Tui\Ansi\AnsiUtils;

/**
 * Composites multiple layers into a single set of ANSI-formatted lines.
 *
 * Layers are applied in order: layer 0 is the base (typically opaque),
 * subsequent layers are painted on top. Transparent layers let the
 * content below show through where no explicit background is set.
 *
 * The canvas dimensions are derived from the first (base) layer:
 * height is the number of lines, width is the visible width of the
 * first line.
 *
 * Usage:
 *
 *     $lines = Compositor::composite(
 *         new Layer($backgroundLines),
 *         new Layer($foregroundLines, transparent: true),
 *     );
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Compositor
{
    /**
     * Composite multiple layers into ANSI-formatted output lines.
     *
     * The first layer defines the canvas dimensions.
     *
     * @return string[]
     */
    public static function composite(Layer ...$layers): array
    {
        if (!$layers) {
            return [];
        }

        $base = $layers[0];
        $height = $base->height ?? \count($base->lines);
        $width = $base->width ?? (!$base->lines ? 0 : AnsiUtils::visibleWidth($base->lines[0]));

        $buffer = new CellBuffer($width, $height);

        foreach ($layers as $layer) {
            $buffer->writeAnsiLines(
                $layer->lines,
                $layer->row,
                $layer->col,
                $layer->transparent,
            );
        }

        return $buffer->toLines();
    }
}
