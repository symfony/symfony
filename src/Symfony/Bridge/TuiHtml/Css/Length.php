<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Css;

use Symfony\Bridge\TuiHtml\Style\CellSize;
use Symfony\Bridge\TuiHtml\Style\MediaContext;

/**
 * Converts CSS lengths to terminal cells.
 *
 * - unitless numbers are cells along the axis
 * - "ch" is a column, "lh" a row: both are exact along their axis, and along the
 *   other one as many cells as a cell is wider or taller, 1ch being half a row
 * - "px" are converted with the cell pixel size, so that 16px is one row on
 *   a 1dppx terminal and still one row on a 2dppx one with twice-taller cells
 * - "em" and "rem" are 16px
 * - the math functions (calc(), min(), max(), clamp(), round(), mod(), the
 *   trigonometric and exponential ones...) combine them, after conversion
 *
 * @experimental
 *
 * @internal
 */
final class Length
{
    /** No terminal is that large: bigger lengths are clamped, so that they cannot allocate without bounds */
    public const MAX_CELLS = 10000;
    /** The math functions of CSS Values, which values can be made of */
    public const MATH_FUNCTIONS = \Symfony\Component\HtmlRenderer\Css\Length::MATH_FUNCTIONS;
    /** What angle units are read as: radians in a trigonometric function, degrees in a hue; null where angles mean nothing */
    private static ?string $angles = null;
    /**
     * @return int|null The number of cells, null when the value is not a length
     */
    /** The width percentages are of while a linear size is evaluated, null when they mean nothing */
    private static ?float $percentBase = null;

    /**
     * Converts a size that may mix percentages and lengths, like calc(100% - 20ch), to a share and cells.
     *
     * Only sizes linear in the room match: min() or max() over a percentage and a length do not.
     *
     * @return array{float, float}|null The percentage of the room and the cells added to it
     */
    public static function linear(string $value, MediaContext $media, bool $vertical = false): ?array
    {
        $value = trim($value);
        if (preg_match('/^(-?(?:\d+\.?\d*|\.\d+))%$/', $value, $m)) {
            return [(float) $m[1], 0.0];
        }
        if (!str_contains($value, '%') || !self::isMath($value)) {
            return null;
        }
        $at = static function (float $base) use ($value, $media, $vertical): ?float {
            self::$percentBase = $base;
            try {
                return self::toFloat($value, $media, $vertical);
            } finally {
                self::$percentBase = null;
            }
        };

        return \Symfony\Component\HtmlRenderer\Css\Length::linear($at);
    }

    /**
     * Converts a tab-size, a number of spaces or a length, to columns; null when invalid, like a negative one.
     */
    public static function tabSize(string $value, MediaContext $media): ?int
    {
        $value = strtolower(trim($value));
        if (is_numeric($value)) {
            return $value >= 0 ? (int) min(64, round((float) $value)) : null;
        }
        $cells = self::toCells($value, $media);

        return null !== $cells && $cells >= 0 ? min(64, $cells) : null;
    }

    public static function toCells(string $value, MediaContext $media, bool $vertical = false): ?int
    {
        $cells = self::toFloat(trim($value), $media, $vertical);
        if (null === $cells) {
            return null;
        }
        // Like CSS: NaN is 0 at the top level, and infinities are clamped
        if (is_nan($cells)) {
            $cells = 0.0;
        }
        $rounded = (int) round(max(-self::MAX_CELLS, min(self::MAX_CELLS, $cells)));
        // A visible length never collapses to nothing
        if (0 === $rounded && $cells > 0) {
            return 1;
        }

        return $rounded;
    }

    /**
     * Converts a length to columns, unrounded, like the radius of a shape; null when it is not a length.
     */
    public static function toColumns(string $value, MediaContext $media): ?float
    {
        $columns = self::toFloat(trim($value), $media, false);

        return null === $columns || is_nan($columns) ? null : max(-self::MAX_CELLS, min(self::MAX_CELLS, $columns));
    }

    /**
     * Converts a vertical length of a box, like the top padding, to rows: a row from three quarters of one, so that
     * the half rows of pages, like padding: .5rem 1rem, leave nothing where a row would double the height of a line.
     *
     * @return int|null The number of rows, null when the value is not a length
     */
    public static function toRows(string $value, MediaContext $media): ?int
    {
        return self::toWholeCells($value, $media, true);
    }

    /**
     * Converts a length to whole cells along an axis, a cell from three quarters of one, like the 2px of an
     * outline-offset, which is none, where toCells() keeps any visible length.
     *
     * @return int|null The number of cells, null when the value is not a length
     */
    public static function toWholeCells(string $value, MediaContext $media, bool $vertical): ?int
    {
        $rows = self::toFloat(trim($value), $media, $vertical);
        if (null === $rows) {
            return null;
        }
        if (is_nan($rows)) {
            return 0;
        }
        $rows = max(-self::MAX_CELLS, min(self::MAX_CELLS, $rows));

        return (int) ($rows < 0 ? -floor(-$rows + 0.25) : floor($rows + 0.25));
    }

    /**
     * Evaluates a math function over angles and numbers of degrees, like a hue, to degrees.
     */
    public static function angle(string $value): ?float
    {
        $angles = self::$angles;
        self::$angles = 'deg';
        try {
            return self::number($value);
        } finally {
            self::$angles = $angles;
        }
    }

    /**
     * Whether a value is a math function, like calc() or round().
     */
    public static function isMath(string $value): bool
    {
        return 1 === preg_match('/^(?:'.implode('|', self::MATH_FUNCTIONS).')\(/i', ltrim($value));
    }

    /**
     * Evaluates a number or a math function over numbers, like the channels of a relative color.
     *
     * Angles are in degrees, like the result of atan(); NaN is 0 and infinities are clamped.
     */
    public static function number(string $value): ?float
    {
        $value = trim($value);
        // Numbers only: a length or a percentage is not a number
        if (preg_match('/(?:\d|\.)(?:px|pt|pc|in|cm|mm|q|r?ch|r?lh|r?ex|r?em|%|[sld]?v(?:w|h|i|b|min|max))/i', $value)) {
            return null;
        }
        $number = self::toFloat($value, new MediaContext(), false);

        return null === $number ? null : (is_nan($number) ? 0.0 : max(-1000000000.0, min(1000000000.0, $number)));
    }

    /**
     * Converts a length or a math function to cells, unrounded.
     */
    private static function toFloat(string $value, MediaContext $media, bool $vertical): ?float
    {
        if (preg_match('/^(-?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?)(px|pt|pc|in|cm|mm|q|r?ch|r?lh|r?ex|em|rem|deg|grad|rad|turn|[sld]?v(?:w|h|i|b|min|max)|)$/i', $value, $m)) {
            $number = (float) $m[1];
            $unit = strtolower($m[2]);
            // Viewport units: shares of the screen, the small, large and dynamic ones alike, as it does not change
            if (preg_match('/^[sld]?v(w|h|i|b|min|max)$/', $unit, $viewport)) {
                return $number / 100 * match ($viewport[1]) {
                    'w', 'i' => $media->columns,
                    'h', 'b' => $media->rows,
                    // A row is two columns tall: the smaller and the larger side, in the cells of the axis asked for
                    'min' => $vertical ? min($media->rows, $media->columns / 2) : min($media->columns, $media->rows * 2),
                    default => $vertical ? max($media->rows, $media->columns / 2) : max($media->columns, $media->rows * 2),
                };
            }

            return match ($unit) {
                '' => $number,
                // ex too, the height of an x about the width of a 0, and the root ones alike, the font being the same: a
                // column, a row being as many as a cell is taller than wide, like the 1ch of 8px of a row of 16px
                'ch', 'ex', 'rch', 'rex' => $vertical ? $number * $media->getCellSize()->width / $media->getCellSize()->height : $number,
                // A row, as many columns as a cell is taller than wide
                'lh', 'rlh' => $vertical ? $number : $number * $media->getCellSize()->height / $media->getCellSize()->width,
                // Absolute lengths, in CSS pixels: 96 an inch, like the pt of a page written in a word processor
                'px', 'pt', 'pc', 'in', 'cm', 'mm', 'q' => self::pixelsToCells(\Symfony\Component\HtmlRenderer\Css\Length::absolute($number, $unit), $media->getCellSize(), $vertical),
                'em', 'rem' => self::pixelsToCells($number * CellSize::REFERENCE_HEIGHT, $media->getCellSize(), $vertical),
                // Angles, in a trigonometric function only
                'deg', 'grad', 'rad', 'turn' => \Symfony\Component\HtmlRenderer\Css\Length::evaluate($value, static fn (string $token): ?float => null, self::$angles),
                default => throw new \UnhandledMatchError(),
            };
        }
        if (null !== self::$percentBase && preg_match('/^(-?(?:\d+\.?\d*|\.\d+))%$/', $value, $m)) {
            return (float) $m[1] * self::$percentBase / 100;
        }
        if (!self::isMath($value)) {
            return null;
        }

        return \Symfony\Component\HtmlRenderer\Css\Length::evaluate($value, static fn (string $token): ?float => self::toFloat($token, $media, $vertical), self::$angles);
    }

    /**
     * CSS pixels are device independent: one CSS pixel is dppx device pixels.
     */
    private static function pixelsToCells(float $pixels, CellSize $cell, bool $vertical): float
    {
        $devicePixels = $pixels * $cell->dppx();

        return $devicePixels / ($vertical ? $cell->height : $cell->width);
    }
}
