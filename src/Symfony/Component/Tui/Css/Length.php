<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Css;

use Symfony\Component\Tui\Style\CellSize;
use Symfony\Component\Tui\Style\MediaContext;

/**
 * Converts CSS lengths to terminal cells.
 *
 * - unitless numbers are cells along the axis
 * - "ch" is a column, "lh" a row: both are exact
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
    public const MAX_CELLS = 10_000;

    /** The math functions of CSS Values, which values can be made of */
    public const MATH_FUNCTIONS = ['calc', 'min', 'max', 'clamp', 'round', 'mod', 'rem', 'abs', 'sign', 'pow', 'sqrt', 'hypot', 'exp', 'log', 'sin', 'cos', 'tan', 'asin', 'acos', 'atan', 'atan2'];

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
        $zero = $at(0.0);
        $hundred = $at(100.0);
        if (null === $zero || null === $hundred || !is_finite($zero) || !is_finite($hundred)) {
            return null;
        }
        // Linear at rooms of other sizes too: min() and max() can be at a few of them, like min(max(5%, 2ch), 50%)
        foreach ([7.0, 33.0, 1000.0] as $room) {
            $size = $at($room);
            if (null === $size || !is_finite($size) || abs(($size - $zero) - $room / 100 * ($hundred - $zero)) > 0.001) {
                return null;
            }
        }

        return [max(-1000.0, min(1000.0, $hundred - $zero)), max(-self::MAX_CELLS, min(self::MAX_CELLS, $zero))];
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

        return null === $number ? null : (is_nan($number) ? 0.0 : max(-1e9, min(1e9, $number)));
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
                // ex too, the height of an x about the width of a 0, and the root ones alike, the font being the same
                '', 'ch', 'lh', 'ex', 'rch', 'rlh', 'rex' => $number,
                'px' => self::pixelsToCells($number, $media->getCellSize(), $vertical),
                // Absolute lengths, in CSS pixels: 96 an inch, like the pt of a page written in a word processor
                'pt' => self::pixelsToCells($number * 4 / 3, $media->getCellSize(), $vertical),
                'pc' => self::pixelsToCells($number * 16, $media->getCellSize(), $vertical),
                'in' => self::pixelsToCells($number * 96, $media->getCellSize(), $vertical),
                'cm' => self::pixelsToCells($number * 96 / 2.54, $media->getCellSize(), $vertical),
                'mm' => self::pixelsToCells($number * 96 / 25.4, $media->getCellSize(), $vertical),
                'q' => self::pixelsToCells($number * 96 / 101.6, $media->getCellSize(), $vertical),
                'em', 'rem' => self::pixelsToCells($number * CellSize::REFERENCE_HEIGHT, $media->getCellSize(), $vertical),
                // Angles, in a trigonometric function only
                'deg', 'grad', 'rad', 'turn' => null === self::$angles ? null : self::angleIn(self::$angles, match (strtolower($m[2])) {
                    'deg' => $number,
                    'grad' => $number * 0.9,
                    'rad' => rad2deg($number),
                    default => $number * 360,
                }),
            };
        }

        if (null !== self::$percentBase && preg_match('/^(-?(?:\d+\.?\d*|\.\d+))%$/', $value, $m)) {
            return (float) $m[1] * self::$percentBase / 100;
        }

        if (!self::isMath($value)) {
            return null;
        }

        $tokens = self::tokenize($value);
        if (null === $tokens) {
            return null;
        }
        $position = 0;
        $result = self::parseSum($tokens, $position, $media, $vertical);

        return $position === \count($tokens) ? $result : null;
    }

    /**
     * @return list<string>|null
     */
    private static function tokenize(string $value): ?array
    {
        if (!preg_match_all('/\s*((?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?[a-z%]*|[a-z][a-z0-9-]*\(|[a-z][a-z-]*|[()+*\/,-])/iA', $value, $m) || implode('', $m[0]) !== rtrim($value)) {
            return null;
        }

        return array_map('strtolower', $m[1]);
    }

    /**
     * @param list<string> $tokens
     */
    private static function parseSum(array $tokens, int &$position, MediaContext $media, bool $vertical): ?float
    {
        $result = self::parseProduct($tokens, $position, $media, $vertical);
        while (null !== $result && \in_array($tokens[$position] ?? null, ['+', '-'], true)) {
            $operator = $tokens[$position++];
            $operand = self::parseProduct($tokens, $position, $media, $vertical);
            if (null === $operand) {
                return null;
            }
            $result = '+' === $operator ? $result + $operand : $result - $operand;
        }

        return $result;
    }

    /**
     * @param list<string> $tokens
     */
    private static function parseProduct(array $tokens, int &$position, MediaContext $media, bool $vertical): ?float
    {
        $result = self::parseFactor($tokens, $position, $media, $vertical);
        while (null !== $result && \in_array($tokens[$position] ?? null, ['*', '/'], true)) {
            $operator = $tokens[$position++];
            $operand = self::parseFactor($tokens, $position, $media, $vertical);
            if (null === $operand || ('/' === $operator && 0.0 === $operand)) {
                return null;
            }
            $result = '*' === $operator ? $result * $operand : $result / $operand;
        }

        return $result;
    }

    /**
     * @param list<string> $tokens
     */
    private static function parseFactor(array $tokens, int &$position, MediaContext $media, bool $vertical): ?float
    {
        $token = $tokens[$position++] ?? null;

        if ('-' === $token) {
            $factor = self::parseFactor($tokens, $position, $media, $vertical);

            return null === $factor ? null : -$factor;
        }

        if ('(' === $token || 'calc(' === $token) {
            $result = self::parseSum($tokens, $position, $media, $vertical);

            return ')' === ($tokens[$position++] ?? null) ? $result : null;
        }

        // The constants of CSS Values
        $constant = match ($token) {
            'e' => \M_E,
            'pi' => \M_PI,
            'infinity' => \INF,
            'nan' => \NAN,
            default => null,
        };
        if (null !== $constant) {
            return $constant;
        }

        if (null !== $token && str_ends_with($token, '(') && \in_array($function = substr($token, 0, -1), self::MATH_FUNCTIONS, true)) {
            return self::parseFunction($function, $tokens, $position, $media, $vertical);
        }

        return null === $token || str_ends_with($token, '(') ? null : self::toFloat($token, $media, $vertical);
    }

    /**
     * Evaluates a math function from its comma-separated arguments.
     *
     * @param list<string> $tokens
     */
    private static function parseFunction(string $function, array $tokens, int &$position, MediaContext $media, bool $vertical): ?float
    {
        // round() starts with its rounding strategy
        $strategy = 'nearest';
        if ('round' === $function && \in_array($tokens[$position] ?? null, ['nearest', 'up', 'down', 'to-zero'], true)) {
            $strategy = $tokens[$position++];
            if (',' !== ($tokens[$position++] ?? null)) {
                return null;
            }
        }

        $angles = self::$angles;
        // Angles in radians for a trigonometric function, through the functions nested in it too
        if (\in_array($function, ['sin', 'cos', 'tan'], true)) {
            self::$angles = 'rad';
        }
        try {
            $arguments = [];
            do {
                $argument = self::parseSum($tokens, $position, $media, $vertical);
                if (null === $argument) {
                    return null;
                }
                $arguments[] = $argument;
            } while (',' === ($tokens[$position] ?? null) && ++$position);
        } finally {
            self::$angles = $angles;
        }

        if (')' !== ($tokens[$position++] ?? null)) {
            return null;
        }

        $count = \count($arguments);
        [$a, $b] = $arguments + [1 => null];

        return match ($function) {
            'calc' => 1 === $count ? $a : null,
            'min' => min($arguments),
            'max' => max($arguments),
            'clamp' => 3 === $count ? max($arguments[0], min($arguments[1], $arguments[2])) : null,
            // Rounded to a multiple of the step, 1 by default: a whole number of cells
            'round' => $count <= 2 ? self::round($strategy, $a, $b ?? 1.0) : null,
            'mod' => 2 === $count ? (0.0 === $b ? \NAN : $a - $b * floor($a / $b)) : null,
            'rem' => 2 === $count ? (0.0 === $b ? \NAN : fmod($a, $b)) : null,
            'abs' => 1 === $count ? abs($a) : null,
            'sign' => 1 === $count ? ($a <=> 0.0) * 1.0 : null,
            'pow' => 2 === $count ? $a ** $b : null,
            'sqrt' => 1 === $count ? ($a < 0 ? \NAN : sqrt($a)) : null,
            'hypot' => sqrt(array_sum(array_map(static fn (float $x): float => $x * $x, $arguments))),
            'exp' => 1 === $count ? exp($a) : null,
            'log' => $count <= 2 ? ($a <= 0 ? ($a < 0 ? \NAN : -\INF) : (null === $b ? log($a) : log($a, $b))) : null,
            'sin' => 1 === $count ? sin($a) : null,
            'cos' => 1 === $count ? cos($a) : null,
            'tan' => 1 === $count ? tan($a) : null,
            // Angles, in degrees
            'asin' => 1 === $count ? rad2deg(asin($a)) : null,
            'acos' => 1 === $count ? rad2deg(acos($a)) : null,
            'atan' => 1 === $count ? rad2deg(atan($a)) : null,
            'atan2' => 2 === $count ? rad2deg(atan2($a, $b)) : null,
            default => null,
        };
    }

    private static function angleIn(string $unit, float $degrees): float
    {
        return 'rad' === $unit ? deg2rad($degrees) : $degrees;
    }

    /**
     * Rounds to a multiple of the step, like round() of CSS Values.
     */
    private static function round(string $strategy, float $value, float $step): float
    {
        if (0.0 === $step) {
            return \NAN;
        }
        $step = abs($step);
        if (!is_finite($value)) {
            return $value;
        }
        if (!is_finite($step)) {
            // Infinitely large steps: 0, or the infinity the strategy goes to
            return match ($strategy) {
                'up' => $value > 0 ? \INF : 0.0,
                'down' => $value < 0 ? -\INF : 0.0,
                default => 0.0,
            };
        }

        $multiple = $value / $step;

        return $step * match ($strategy) {
            'up' => ceil($multiple),
            'down' => floor($multiple),
            'to-zero' => $multiple < 0 ? ceil($multiple) : floor($multiple),
            // Half way rounds up, towards positive infinity
            default => floor($multiple + 0.5),
        };
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
