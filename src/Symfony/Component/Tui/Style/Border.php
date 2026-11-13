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

    public function __construct(
        int $top,
        int $right,
        int $bottom,
        int $left,
        BorderPattern|string|null $pattern = null,
        Color|string|int|null $color = null,
    ) {
        $this->top = max(0, $top);
        $this->right = max(0, $right);
        $this->bottom = max(0, $bottom);
        $this->left = max(0, $left);
        $this->pattern = self::normalizePattern($pattern);
        $this->color = null !== $color ? Color::from($color) : null;
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
     * Create a new border with a different pattern.
     */
    public function withPattern(BorderPattern|string|null $pattern): self
    {
        return new self($this->top, $this->right, $this->bottom, $this->left, $pattern, $this->color);
    }

    /**
     * Create a new border with a different color.
     */
    public function withColor(Color|string|int|null $color): self
    {
        return new self($this->top, $this->right, $this->bottom, $this->left, $this->pattern, $color);
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
        $pattern = $this->pattern;
        $chars = $pattern->getChars();
        $strategies = $pattern->getStrategies();

        $outerStyle ??= new Style();
        $borderColor = $this->color ?? $innerStyle->getColor();

        $lines = [];

        if ($this->top > 0) {
            for ($row = 0; $row < $this->top; ++$row) {
                $lines[] = $this->buildBorderRow($pattern, $chars[0], $strategies[0], $innerWidth, $this->left, $this->right, $outerStyle, $innerStyle, $borderColor);
            }
        }

        $leftSegment = $this->left > 0 ? $pattern->applyBorderSegment(str_repeat('' !== $chars[1][0] ? $chars[1][0] : ' ', $this->left), $strategies[1][0], $outerStyle, $innerStyle, $borderColor) : '';
        $rightSegment = $this->right > 0 ? $pattern->applyBorderSegment(str_repeat('' !== $chars[1][2] ? $chars[1][2] : ' ', $this->right), $strategies[1][2], $outerStyle, $innerStyle, $borderColor) : '';

        foreach ($innerLines as $line) {
            $lines[] = $leftSegment.$line.$rightSegment;
        }

        if ($this->bottom > 0) {
            for ($row = 0; $row < $this->bottom; ++$row) {
                $lines[] = $this->buildBorderRow($pattern, $chars[2], $strategies[2], $innerWidth, $this->left, $this->right, $outerStyle, $innerStyle, $borderColor);
            }
        }

        return $lines;
    }

    /**
     * @param array<int, string> $chars
     * @param array<int, int>    $strategies
     */
    private function buildBorderRow(BorderPattern $pattern, array $chars, array $strategies, int $innerWidth, int $leftWidth, int $rightWidth, Style $outerStyle, Style $innerStyle, ?Color $borderColor): string
    {
        $left = $leftWidth > 0
            ? $pattern->applyBorderSegment(str_repeat('' !== $chars[0] ? $chars[0] : ' ', $leftWidth), $strategies[0], $outerStyle, $innerStyle, $borderColor)
            : '';
        $middle = $pattern->applyBorderSegment(str_repeat('' !== $chars[1] ? $chars[1] : ' ', $innerWidth), $strategies[1], $outerStyle, $innerStyle, $borderColor);
        $right = $rightWidth > 0
            ? $pattern->applyBorderSegment(str_repeat('' !== $chars[2] ? $chars[2] : ' ', $rightWidth), $strategies[2], $outerStyle, $innerStyle, $borderColor)
            : '';

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
