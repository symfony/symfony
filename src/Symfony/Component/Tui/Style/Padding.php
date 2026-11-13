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
 * Represents padding values like CSS padding.
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
final class Padding
{
    public readonly int $top;
    public readonly int $right;
    public readonly int $bottom;
    public readonly int $left;

    public function __construct(int $top, int $right, int $bottom, int $left)
    {
        $this->top = max(0, $top);
        $this->right = max(0, $right);
        $this->bottom = max(0, $bottom);
        $this->left = max(0, $left);
    }

    /**
     * Create a Padding from various input formats.
     *
     * @param self|array<int> $padding Padding specification:
     *                                 - Padding instance: returned as-is
     *                                 - array with 1 element: all sides
     *                                 - array with 2 elements: [top/bottom, left/right]
     *                                 - array with 3 elements: [top, left/right, bottom]
     *                                 - array with 4 elements: [top, right, bottom, left]
     */
    public static function from(self|array $padding): self
    {
        if ($padding instanceof self) {
            return $padding;
        }

        return match (\count($padding)) {
            1 => new self($padding[0], $padding[0], $padding[0], $padding[0]),
            2 => new self($padding[0], $padding[1], $padding[0], $padding[1]),
            3 => new self($padding[0], $padding[1], $padding[2], $padding[1]),
            4 => new self($padding[0], $padding[1], $padding[2], $padding[3]),
            default => throw new InvalidArgumentException('Padding array must have 1, 2, 3, or 4 elements.'),
        };
    }

    /**
     * Create padding with all sides equal.
     */
    public static function all(int $value): self
    {
        return new self($value, $value, $value, $value);
    }

    /**
     * Create padding with horizontal and vertical values.
     *
     * @param int $x Left/right padding
     * @param int $y Top/bottom padding
     */
    public static function xy(int $x, int $y = 0): self
    {
        return new self($y, $x, $y, $x);
    }

    /**
     * Get horizontal padding (left + right).
     */
    public function getHorizontal(): int
    {
        return $this->left + $this->right;
    }

    /**
     * Get vertical padding (top + bottom).
     */
    public function getVertical(): int
    {
        return $this->top + $this->bottom;
    }
}
