<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Render;

use Symfony\Bridge\TuiHtml\Internal\TextSize;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Layout\LayoutMetricsInterface;
use Symfony\Component\HtmlRenderer\Layout\RemainderAllocation;

final readonly class TerminalLayoutMetrics implements LayoutMetricsInterface
{
    public function __construct(private TerminalTextMeasurer $metrics = new TerminalTextMeasurer(), private bool $reserveOutsideMarkers = true)
    {
    }

    public function scrollbarThickness(bool $vertical, bool $thin = false): float
    {
        return $vertical ? $this->metrics->cellWidth : $this->metrics->cellHeight;
    }

    public function outsideMarkerGutter(float $naturalGroupWidth): float
    {
        return $this->reserveOutsideMarkers ? max(0.0, $this->constrainLength($naturalGroupWidth, 'padding-inline-start', false)) : 0.0;
    }

    public function resolveUnit(float $number, string $unit, bool $vertical, float $resolved): float
    {
        return match ($unit) {
            '' => $number * ($vertical ? $this->metrics->cellHeight : $this->metrics->cellWidth),
            'ch', 'ex', 'rch', 'rex' => $number * $this->metrics->cellWidth,
            'lh', 'rlh' => $number * $this->metrics->cellHeight,
            'em', 'rem' => $number * $this->metrics->cellHeight,
            'px', 'pt', 'pc', 'in', 'cm', 'mm', 'q' => $resolved * $this->metrics->cellHeight / 16,
            default => $resolved,
        };
    }

    public function constrainLength(float $length, string $property, bool $vertical, ?FontStyle $font = null): float
    {
        if ('computed-font-advance' === $property) {
            return $length / $this->resolveUnit(1, 'px', false, 1);
        }
        if (str_starts_with($property, 'inline-border-')) {
            // Like the retained inline renderer: side cells decorate the text line; no extra border rows.
            if ($vertical) {
                return 0.0;
            }
            $property = substr($property, 7);
        }
        if ('line-height' === $property && null !== $font && $this->metrics->textSizing) {
            $length = max($length, $this->metrics->cellHeight * TextSize::scale($font->size));
        }
        if ('view-timeline-viewport' === $property) {
            $cell = $vertical ? $this->metrics->cellHeight : $this->metrics->cellWidth;

            return max($cell, round($length / $cell) * $cell);
        }
        if ('inline-baseline-position' === $property) {
            return floor($length / $this->metrics->cellHeight) * $this->metrics->cellHeight;
        }
        if ('anchor-center-offset' === $property) {
            $cell = $vertical ? $this->metrics->cellHeight : $this->metrics->cellWidth;

            return (int) ($length / $cell) * $cell;
        }
        if (\in_array($property, ['vertical-align-super', 'vertical-align-sub'], true)) {
            return 0.0;
        }
        if ('background-intrinsic-width' === $property) {
            return max(1.0, ceil($length / 8)) * $this->metrics->cellWidth;
        }
        if ('background-intrinsic-height' === $property) {
            return max(1.0, round($length / (2 * $this->metrics->cellWidth))) * $this->metrics->cellHeight;
        }
        if (\in_array($property, ['background-size', 'image-object-size'], true)) {
            $cell = $vertical ? $this->metrics->cellHeight : $this->metrics->cellWidth;

            return $length <= 0 ? 0.0 : max(1.0, min(1000.0, round($length / $cell))) * $cell;
        }
        $cell = $vertical ? $this->metrics->cellHeight : $this->metrics->cellWidth;
        $cells = max(-10000.0, min(10000.0, $length / $cell));
        if (str_starts_with($property, 'border-') && str_ends_with($property, '-width') || 'border-width' === $property || 'outline-width' === $property) {
            return $cells > 0 ? $cell : 0.0;
        }
        if ($vertical && (str_starts_with($property, 'margin') || str_starts_with($property, 'padding') || \in_array($property, ['gap', 'row-gap'], true))) {
            return ($cells < 0 ? -floor(-$cells + 0.25) : floor($cells + 0.25)) * $cell;
        }
        if ('outline-offset' === $property) {
            return max(-100.0, min(100.0, $cells < 0 ? -floor(-$cells + 0.25) : floor($cells + 0.25))) * $cell;
        }
        if ('scroll-snap-proximity' === $property) {
            return floor($cells) * $cell;
        }
        if ('percentage-inset' === $property) {
            return (int) $cells * $cell;
        }
        if (\in_array($property, ['letter-spacing', 'word-spacing'], true)) {
            return $length;
        }
        $rounded = round($cells);
        if ('scroll-control-page' === $property || 'keyboard-scroll-step' === $property && !$vertical) {
            return max(1.0, floor($cells)) * $cell;
        }
        if (\in_array($property, ['scrollbar-thumb-offset', 'background-position', 'object-position', 'scroll-snap-position'], true)) {
            return $rounded * $cell;
        }

        return (0.0 === $rounded && $cells > 0 ? 1.0 : $rounded) * $cell;
    }

    public function distribute(array $lengths, bool $vertical): array
    {
        $cell = $vertical ? $this->metrics->cellHeight : $this->metrics->cellWidth;
        $cells = RemainderAllocation::round(array_map(static fn (float $length): float => $length / $cell, $lengths));

        return array_map(static fn (int $length): float => $length * $cell, $cells);
    }
}
