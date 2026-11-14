<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * Represents border values like CSS border.
 *
 * Supports 1, 2, 3, or 4 values:
 * - 1 value:  all sides
 * - 2 values: top/bottom, left/right
 * - 3 values: top, left/right, bottom
 * - 4 values: top, right, bottom, left
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Border
{
    private const DEFAULT_PATTERN = BorderPattern::NORMAL;
    public readonly int $top;
    public readonly int $right;
    public readonly int $bottom;
    public readonly int $left;
    public readonly BorderPattern $pattern;
    public readonly ?Color $color;
    /**
     * The colors of the top, right, bottom and left sides, each one null for the color of the border.
     *
     * @var array{?Color, ?Color, ?Color, ?Color}
     */
    public readonly array $sideColors;
    /**
     * The patterns of the top, right, bottom and left sides, each one null for the pattern of the border.
     *
     * @var array{?BorderPattern, ?BorderPattern, ?BorderPattern, ?BorderPattern}
     */
    public readonly array $sidePatterns;
    /**
     * The shapes of the top-left, top-right, bottom-right and bottom-left corners, each one null for the corner of
     * the patterns: "round" (╭) or "bevel" (╱), like CSS border-radius and corner-shape.
     *
     * @var array{?string, ?string, ?string, ?string}
     */
    public readonly array $cornerShapes;

    /**
     * @param array<int, Color|string|int|null>|null     $sideColors   The colors of the top, right, bottom and left sides, over the color of the border
     * @param array<int, BorderPattern|string|null>|null $sidePatterns The patterns of the top, right, bottom and left sides, over the pattern of the border
     * @param array<int, 'round'|'bevel'|null>|null      $cornerShapes The shapes of the top-left, top-right, bottom-right and bottom-left corners
     */
    public function __construct(int $top, int $right, int $bottom, int $left, BorderPattern|string|null $pattern = null, Color|string|int|null $color = null, ?array $sideColors = null, ?array $sidePatterns = null, ?array $cornerShapes = null)
    {
        $this->top = max(0, $top);
        $this->right = max(0, $right);
        $this->bottom = max(0, $bottom);
        $this->left = max(0, $left);
        $this->pattern = self::normalizePattern($pattern);
        $this->color = null !== $color ? Color::from($color) : null;
        $sides = [];
        foreach ([0, 1, 2, 3] as $side) {
            $sides[] = null !== ($sideColors[$side] ?? null) ? Color::from($sideColors[$side]) : null;
        }
        $this->sideColors = [$sides[0], $sides[1], $sides[2], $sides[3]];
        $patterns = [];
        foreach ([0, 1, 2, 3] as $side) {
            $patterns[] = null !== ($sidePatterns[$side] ?? null) ? self::normalizePattern($sidePatterns[$side]) : null;
        }
        $this->sidePatterns = [$patterns[0], $patterns[1], $patterns[2], $patterns[3]];
        $shapes = [];
        foreach ([0, 1, 2, 3] as $corner) {
            $shapes[] = \in_array($cornerShapes[$corner] ?? null, ['round', 'bevel'], true) ? $cornerShapes[$corner] : null;
        }
        $this->cornerShapes = [$shapes[0], $shapes[1], $shapes[2], $shapes[3]];
    }

    /**
     * Create a Border from various input formats.
     *
     * @param self|array<int> $border Border specification:
     *                                - Border instance: returned as-is
     *                                - array with 1 element: all sides
     *                                - array with 2 elements: [top/bottom, left/right]
     *                                - array with 3 elements: [top, left/right, bottom]
     *                                - array with 4 elements: [top, right, bottom, left]
     */
    public static function from(self|array $border, BorderPattern|string|null $pattern = null, Color|string|int|null $color = null): self
    {
        if ($border instanceof self) {
            if (null === $pattern && null === $color) {
                return $border;
            }

            return new self($border->top, $border->right, $border->bottom, $border->left, $pattern ?? $border->pattern, $color ?? $border->color);
        }

        return match (\count($border)) {
            1 => new self($border[0], $border[0], $border[0], $border[0], $pattern, $color),
            2 => new self($border[0], $border[1], $border[0], $border[1], $pattern, $color),
            3 => new self($border[0], $border[1], $border[2], $border[1], $pattern, $color),
            4 => new self($border[0], $border[1], $border[2], $border[3], $pattern, $color),
            default => throw new InvalidArgumentException('Border array must have 1, 2, 3, or 4 elements.'),
        };
    }

    /**
     * Create a border with all sides equal.
     */
    public static function all(int $value, BorderPattern|string|null $pattern = null, Color|string|int|null $color = null): self
    {
        return new self($value, $value, $value, $value, $pattern, $color);
    }

    /**
     * Create a border with horizontal and vertical values.
     *
     * @param int $x Left/right border
     * @param int $y Top/bottom border
     */
    public static function xy(int $x, int $y = 0, BorderPattern|string|null $pattern = null, Color|string|int|null $color = null): self
    {
        return new self($y, $x, $y, $x, $pattern, $color);
    }

    /**
     * Get horizontal border (left + right).
     */
    public function getHorizontal(): int
    {
        return $this->left + $this->right;
    }

    /**
     * Get vertical border (top + bottom).
     */
    public function getVertical(): int
    {
        return $this->top + $this->bottom;
    }

    /**
     * Create a new border with a different pattern, for all its sides.
     */
    public function withPattern(BorderPattern|string|null $pattern): self
    {
        return new self($this->top, $this->right, $this->bottom, $this->left, $pattern, $this->color, $this->sideColors, null, $this->cornerShapes);
    }

    /**
     * Create a new border with a pattern for some sides, like CSS border-top-style: 0 is the top, then clockwise.
     *
     * @param array<int, BorderPattern|string|null> $patterns The patterns by side, null for the pattern of the border
     */
    public function withSidePatterns(array $patterns): self
    {
        $sides = $this->sidePatterns;
        foreach ($patterns as $side => $pattern) {
            if (\in_array($side, [0, 1, 2, 3], true)) {
                $sides[$side] = $pattern;
            }
        }

        return new self($this->top, $this->right, $this->bottom, $this->left, $this->pattern, $this->color, $this->sideColors, $sides, $this->cornerShapes);
    }

    /**
     * The pattern of a side: 0 is the top, then clockwise.
     */
    public function getSidePattern(int $side): BorderPattern
    {
        return $this->sidePatterns[$side] ?? $this->pattern;
    }

    /**
     * Create a new border with other widths, the same patterns and colors.
     */
    public function withWidths(int $top, int $right, int $bottom, int $left): self
    {
        return new self($top, $right, $bottom, $left, $this->pattern, $this->color, $this->sideColors, $this->sidePatterns, $this->cornerShapes);
    }

    /**
     * Create a new border with a color for some sides, like CSS border-top-color: 0 is the top, then clockwise.
     *
     * @param array<int, Color|string|int|null> $colors The colors by side
     */
    public function withSideColors(array $colors): self
    {
        $sides = $this->sideColors;
        foreach ($colors as $side => $color) {
            if (\in_array($side, [0, 1, 2, 3], true)) {
                $sides[$side] = null !== $color ? Color::from($color) : null;
            }
        }

        return new self($this->top, $this->right, $this->bottom, $this->left, $this->pattern, $this->color, $sides, $this->sidePatterns, $this->cornerShapes);
    }

    /**
     * Create a new border with a different color, for all its sides.
     */
    public function withColor(Color|string|int|null $color): self
    {
        return new self($this->top, $this->right, $this->bottom, $this->left, $this->pattern, $color, null, $this->sidePatterns, $this->cornerShapes);
    }

    /**
     * Create a new border with shaped corners, like CSS border-radius and corner-shape: 0 is the top-left one, then
     * clockwise.
     *
     * @param array<int, 'round'|'bevel'|null> $shapes "round" (╭), "bevel" (╱), or null for the corner of the patterns
     */
    public function withCornerShapes(array $shapes): self
    {
        return new self($this->top, $this->right, $this->bottom, $this->left, $this->pattern, $this->color, $this->sideColors, $this->sidePatterns, $shapes);
    }

    /**
     * @param string[] $innerLines
     *
     * @return string[]
     *
     * @internal
     */
    public function wrapLines(array $innerLines, int $innerWidth, Style $innerStyle, ?Style $outerStyle = null): array
    {
        if ([null, null, null, null] !== $this->sidePatterns) {
            return $this->wrapLinesBySide($innerLines, $innerWidth, $innerStyle, $outerStyle ?? new Style());
        }
        $pattern = $this->pattern;
        $chars = $pattern->getChars();
        if ([null, null, null, null] !== $this->cornerShapes) {
            $chars[0][0] = $this->cornerChar(0, $chars[0][0]);
            $chars[0][2] = $this->cornerChar(1, $chars[0][2]);
            $chars[2][2] = $this->cornerChar(2, $chars[2][2]);
            $chars[2][0] = $this->cornerChar(3, $chars[2][0]);
        }
        $strategies = $pattern->getStrategies();
        $outerStyle ??= new Style();
        $borderColor = $this->color ?? $innerStyle->getColor();
        [$topColor, $rightColor, $bottomColor, $leftColor] = array_map(static fn (?Color $color) => $color ?? $borderColor, $this->sideColors);
        $lines = [];
        if ($this->top > 0) {
            for ($row = 0; $row < $this->top; ++$row) {
                $lines[] = $this->buildBorderRow($pattern, $chars[0], $strategies[0], $innerWidth, $this->left, $this->right, $outerStyle, $innerStyle, $topColor);
            }
        }
        $leftSegment = $this->left > 0 ? $pattern->applyBorderSegment(str_repeat('' !== $chars[1][0] ? $chars[1][0] : ' ', $this->left), $strategies[1][0], $outerStyle, $innerStyle, $leftColor) : '';
        $rightSegment = $this->right > 0 ? $pattern->applyBorderSegment(str_repeat('' !== $chars[1][2] ? $chars[1][2] : ' ', $this->right), $strategies[1][2], $outerStyle, $innerStyle, $rightColor) : '';
        // A line across the box touching its sides, like the bottom border of the header of a code example of MDN:
        // their lines join, │ and ─ making ├ and ┤
        $joins = 1 === $this->left && 1 === $this->right && '│' === $chars[1][0] && '│' === $chars[1][2];
        $joined = null;
        // The same line wrapped once: the rows alike, like those of a tall margin, share their string
        $wrapped = [];
        foreach ($innerLines as $line) {
            if (!isset($wrapped[$line]) && $joins && str_contains($line, '─') && str_repeat('─', $innerWidth) === AnsiUtils::stripAnsiCodes($line)) {
                $joined ??= [$pattern->applyBorderSegment('├', $strategies[1][0], $outerStyle, $innerStyle, $leftColor), $pattern->applyBorderSegment('┤', $strategies[1][2], $outerStyle, $innerStyle, $rightColor)];
                $wrapped[$line] = $joined[0].$line.$joined[1];
            }
            $lines[] = $wrapped[$line] ??= $leftSegment.$line.$rightSegment;
        }
        if ($this->bottom > 0) {
            for ($row = 0; $row < $this->bottom; ++$row) {
                $lines[] = $this->buildBorderRow($pattern, $chars[2], $strategies[2], $innerWidth, $this->left, $this->right, $outerStyle, $innerStyle, $bottomColor);
            }
        }

        return $lines;
    }

    /**
     * The character of a corner, from the one the patterns draw there: rounded when it is a light corner, beveled
     * when it is a line.
     *
     * @param int $corner 0 is the top-left one, then clockwise
     */
    private function cornerChar(int $corner, string $char): string
    {
        return match ($this->cornerShapes[$corner]) {
            'round' => ['┌' => '╭', '┐' => '╮', '┘' => '╯', '└' => '╰'][$char] ?? $char,
            'bevel' => '' === trim($char) ? $char : (0 === $corner % 2 ? '╱' : '╲'),
            default => $char,
        };
    }

    /**
     * Draws a border whose sides have their own patterns, the corners joining them.
     *
     * @param string[] $innerLines
     *
     * @return string[]
     */
    private function wrapLinesBySide(array $innerLines, int $innerWidth, Style $innerStyle, Style $outerStyle): array
    {
        [$top, $right, $bottom, $left] = array_map($this->getSidePattern(...), [0, 1, 2, 3]);
        $borderColor = $this->color ?? $innerStyle->getColor();
        [$topColor, $rightColor, $bottomColor, $leftColor] = array_map(static fn (?Color $color) => $color ?? $borderColor, $this->sideColors);
        // A segment of a pattern, at a row and column of its 3x3 grid
        $segment = static function (BorderPattern $pattern, int $row, int $column, int $width, ?Color $color, ?string $char = null) use ($outerStyle, $innerStyle): string {
            $char ??= $pattern->getChars()[$row][$column];

            return $width > 0 ? $pattern->applyBorderSegment(str_repeat('' !== $char ? $char : ' ', $width), $pattern->getStrategies()[$row][$column], $outerStyle, $innerStyle, $color) : '';
        };
        $row = fn (BorderPattern $pattern, int $row, ?Color $color): string => $segment($pattern, $row, 0, $this->left, $color, $this->cornerChar(0 === $row ? 0 : 3, BorderPattern::corner($pattern, $left, 0 === $row ? 0 : 2, 0))).$segment($pattern, $row, 1, $innerWidth, $color).$segment($pattern, $row, 2, $this->right, $color, $this->cornerChar(0 === $row ? 1 : 2, BorderPattern::corner($pattern, $right, 0 === $row ? 0 : 2, 2)));
        $lines = [];
        for ($i = 0; $i < $this->top; ++$i) {
            $lines[] = $row($top, 0, $topColor);
        }
        $leftSegment = $segment($left, 1, 0, $this->left, $leftColor);
        $rightSegment = $segment($right, 1, 2, $this->right, $rightColor);
        // A line across the box touching its sides, like the line between the rows of the files of GitHub: joined
        $joins = 1 === $this->left && 1 === $this->right && '│' === $left->getChars()[1][0] && '│' === $right->getChars()[1][2];
        // The same line wrapped once: the rows alike, like those of a tall margin, share their string
        $wrapped = [];
        foreach ($innerLines as $line) {
            if (!isset($wrapped[$line]) && $joins && str_contains($line, '─') && str_repeat('─', $innerWidth) === AnsiUtils::stripAnsiCodes($line)) {
                $wrapped[$line] = $segment($left, 1, 0, 1, $leftColor, '├').$line.$segment($right, 1, 2, 1, $rightColor, '┤');
            }
            $lines[] = $wrapped[$line] ??= $leftSegment.$line.$rightSegment;
        }
        for ($i = 0; $i < $this->bottom; ++$i) {
            $lines[] = $row($bottom, 2, $bottomColor);
        }

        return $lines;
    }

    /**
     * @param array<int, string> $chars
     * @param array<int, int>    $strategies
     */
    private function buildBorderRow(BorderPattern $pattern, array $chars, array $strategies, int $innerWidth, int $leftWidth, int $rightWidth, Style $outerStyle, Style $innerStyle, ?Color $borderColor): string
    {
        $left = $leftWidth > 0 ? $pattern->applyBorderSegment(str_repeat('' !== $chars[0] ? $chars[0] : ' ', $leftWidth), $strategies[0], $outerStyle, $innerStyle, $borderColor) : '';
        $middle = $pattern->applyBorderSegment(str_repeat('' !== $chars[1] ? $chars[1] : ' ', $innerWidth), $strategies[1], $outerStyle, $innerStyle, $borderColor);
        $right = $rightWidth > 0 ? $pattern->applyBorderSegment(str_repeat('' !== $chars[2] ? $chars[2] : ' ', $rightWidth), $strategies[2], $outerStyle, $innerStyle, $borderColor) : '';

        return $left.$middle.$right;
    }

    private static function normalizePattern(BorderPattern|string|null $pattern): BorderPattern
    {
        if ($pattern instanceof BorderPattern) {
            return $pattern;
        }

        return BorderPattern::fromName($pattern ?? self::DEFAULT_PATTERN);
    }
}
