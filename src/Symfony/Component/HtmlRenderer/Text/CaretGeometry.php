<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Text;

use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\Layout\Fragment;
use Symfony\Component\HtmlRenderer\Layout\LayoutMetricsInterface;

/** @internal */
final class CaretGeometry
{
    public static function bounds(Fragment $fragment, TextMeasurerInterface $measurer, ?LayoutMetricsInterface $layoutMetrics = null): ?Rect
    {
        $run = $fragment->run;
        $selection = $run?->selection;
        if (null === $selection || !$selection->caretVisible || $selection->focus < $run->start || $selection->focus > $run->end || null === $fragment->text) {
            return null;
        }
        preg_match_all('/\X/u', $fragment->text, $matches);
        $count = \count($matches[0]);
        $caret = max(0, min($count, $run->toDisplay($selection->focus)));
        $style = $fragment->style;
        $font = new FontStyle($style['font-family'] ?? 'sans-serif', (float) ($style['font-size'] ?? 16), $fragment->bounds->height, (int) ($style['font-weight'] ?? 400), $style['direction'] ?? 'ltr', $style['font-style'] ?? 'normal', (float) ($style['letter-spacing'] ?? 0), (float) ($style['word-spacing'] ?? 0));
        $positions = $run->caretPositions ?? $measurer->measure($fragment->text, $font)->caretPositions;
        $advance = $positions[$caret] ?? ($caret === $count ? $fragment->bounds->width : $measurer->measure(implode('', \array_slice($matches[0], 0, $caret)), $font)->width);

        $width = $layoutMetrics?->constrainLength(1.0, 'caret-width', false) ?? 1.0;

        return new Rect($fragment->bounds->x + $advance, $fragment->bounds->y, max(0.0, $width), $fragment->bounds->height);
    }
}
