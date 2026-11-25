<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Internal;

use Symfony\Component\HtmlRenderer\Layout\ScrollbarGeometry;

/** @internal */
final class ScrollbarPresentation
{
    /** @return array{int, int} The thumb's length and offset in cells. */
    public static function cells(int $viewport, int $content, int $offset): array
    {
        if ($viewport <= 0) {
            return [0, 0];
        }
        $length = max(1, (int) round(ScrollbarGeometry::thumbLength($viewport, $viewport, $content)));
        $position = (int) round(ScrollbarGeometry::thumbOffset($viewport, $length, $viewport, $content, $offset));

        return [$length, $position];
    }
}
