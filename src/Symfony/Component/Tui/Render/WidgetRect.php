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
 * Represents the absolute position and size of a rendered widget on screen.
 *
 * Coordinates are in terminal character cells, with (0, 0) at the top-left.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class WidgetRect
{
    public function __construct(
        public readonly int $row,
        public readonly int $col,
        public readonly int $columns,
        public readonly int $rows,
    ) {
    }

    /**
     * Check if the given screen coordinates are within this rect.
     *
     * @param int $row 0-indexed row
     * @param int $col 0-indexed column
     */
    public function contains(int $row, int $col): bool
    {
        return $row >= $this->row
            && $row < $this->row + $this->rows
            && $col >= $this->col
            && $col < $this->col + $this->columns;
    }

    /**
     * Convert absolute screen coordinates to widget-relative coordinates.
     *
     * @return array{row: int, col: int} Widget-relative coordinates
     */
    public function toRelative(int $row, int $col): array
    {
        return [
            'row' => $row - $this->row,
            'col' => $col - $this->col,
        ];
    }
}
