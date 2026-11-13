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
    /**
     * @param list<array{int, int, int, int}> $fragments The other pieces of a widget, including visible overflow and pieces across
     *                                                   the columns of a multi-column container: their row and column from those of
     *                                                   this rect, their columns and their rows
     * @param array{int, int, int, int}|null  $clip      What is shown of it, cut by a box that clips: the first row and
     *                                                   column, and the row and column past; the rest takes no pointer
     */
    public function __construct(
        public readonly int $row,
        public readonly int $col,
        public readonly int $columns,
        public readonly int $rows,
        public readonly array $fragments = [],
        public readonly ?array $clip = null,
    ) {
    }

    /**
     * The same rect, only what is inside a clip shown, inside the clip it has already.
     *
     * @param array{int, int, int, int} $clip The first row and column, and the row and column past
     */
    public function withClip(array $clip): self
    {
        if (null !== $this->clip) {
            $clip = [max($clip[0], $this->clip[0]), max($clip[1], $this->clip[1]), min($clip[2], $this->clip[2]), min($clip[3], $this->clip[3])];
        }

        return new self($this->row, $this->col, $this->columns, $this->rows, $this->fragments, $clip);
    }

    /**
     * Check if the given screen coordinates are within this rect.
     *
     * @param int $row 0-indexed row
     * @param int $col 0-indexed column
     */
    public function contains(int $row, int $col): bool
    {
        if (null !== $this->clip && ($row < $this->clip[0] || $col < $this->clip[1] || $row >= $this->clip[2] || $col >= $this->clip[3])) {
            return false;
        }
        if ($row >= $this->row && $row < $this->row + $this->rows && $col >= $this->col && $col < $this->col + $this->columns) {
            return true;
        }
        // In another piece of the widget
        foreach ($this->fragments as [$fragmentRow, $fragmentCol, $columns, $rows]) {
            if ($row >= $this->row + $fragmentRow && $row < $this->row + $fragmentRow + $rows && $col >= $this->col + $fragmentCol && $col < $this->col + $fragmentCol + $columns) {
                return true;
            }
        }

        return false;
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
