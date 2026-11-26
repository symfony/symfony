<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Layout;

/**
 * The participation of table, row and cell sides in the collapsed border grid.
 *
 * Topology is independent of the optional width, pattern and origin winner calculation.
 *
 * @internal
 */
final class CollapsedBorderGrid
{
    /**
     * @param array<int, array<int, int>>         $owners     Cell index at each occupied row and column
     * @param list<array{int, int, int, int}>     $cells      Row, column, row span and column span
     * @param list<array{bool, bool, bool, bool}> $cellSides  Top, right, bottom and left sides
     * @param list<array{bool, bool, bool, bool}> $rowSides   Row sides including applicable group sides
     * @param array{bool, bool, bool, bool}       $tableSides
     */
    public function __construct(private readonly array $owners, private readonly array $cells, private readonly array $cellSides, private readonly array $rowSides, private readonly array $tableSides, private readonly int $columns)
    {
    }

    /**
     * CSS 2.2 section 17.6.2.1: hidden, width, style, origin, then directional position.
     * https://www.w3.org/TR/CSS22/tables.html#border-conflict-resolution.
     *
     * @param list<array{width: float, style: string, color: string, origin: int, row: int, column: int}> $candidates
     */
    public static function winner(array $candidates, bool $rtl = false): ?array
    {
        $winner = null;
        $priority = null;
        $styles = ['inset' => 0, 'groove' => 1, 'outset' => 2, 'ridge' => 3, 'dotted' => 4, 'dashed' => 5, 'solid' => 6, 'double' => 7];
        foreach ($candidates as $candidate) {
            if ('hidden' === $candidate['style']) {
                return array_replace($candidate, ['width' => 0.0]);
            }
            if ('none' === $candidate['style'] || $candidate['width'] <= 0) {
                continue;
            }
            $rank = [$candidate['width'], $styles[$candidate['style']] ?? 0, $candidate['origin'], -$candidate['row'], $rtl ? $candidate['column'] : -$candidate['column']];
            if (null === $priority || $rank > $priority) {
                $winner = $candidate;
                $priority = $rank;
            }
        }

        return $winner;
    }

    /**
     * Borders are centered on grid lines (CSS 2.2 section 17.6.2).
     * A device may split a discrete unit consistently, preserving the full width.
     *
     * @return array{float, float}
     */
    public static function halves(float $width, bool $vertical, ?LayoutMetricsInterface $metrics = null): array
    {
        return $metrics?->distribute([$width / 2, $width / 2], $vertical) ?? [$width / 2, $width / 2];
    }

    public function verticalBoundary(int $row, int $column): bool
    {
        return 0 === $column || $this->columns === $column || $this->owner($row, $column - 1) !== $this->owner($row, $column);
    }

    public function horizontalBoundary(?int $above, ?int $below, int $column): bool
    {
        return null === $above || null === $below || $this->owner($above, $column) !== $this->owner($below, $column) || $this->owner($above, $column) < 0;
    }

    public function vertical(?int $row, int $column): bool
    {
        return null !== $row && match (true) {
            0 === $column => $this->tableSides[3] || $this->rowSides[$row][3] || $this->cellSide($row, 0, 3),
            $this->columns === $column => $this->tableSides[1] || $this->rowSides[$row][1] || $this->cellSide($row, $this->columns - 1, 1),
            default => $this->verticalBoundary($row, $column) && ($this->cellSide($row, $column - 1, 1) || $this->cellSide($row, $column, 3)),
        };
    }

    public function horizontal(?int $above, ?int $below, int $column): bool
    {
        return $column >= 0 && $column < $this->columns && (null !== $above || null !== $below)
            && $this->horizontalBoundary($above, $below, $column)
            && ((null === $above ? $this->tableSides[0] : $this->rowSides[$above][2] || $this->cellSide($above, $column, 2)) || (null === $below ? $this->tableSides[2] : $this->rowSides[$below][0] || $this->cellSide($below, $column, 0)));
    }

    private function owner(?int $row, int $column): ?int
    {
        return null === $row || $column < 0 || $column >= $this->columns ? null : $this->owners[$row][$column] ?? -1;
    }

    private function cellSide(int $row, int $column, int $side): bool
    {
        $index = $this->owner($row, $column) ?? -1;
        if ($index < 0) {
            return false;
        }
        [$firstRow, $firstColumn, $rows, $columns] = $this->cells[$index];

        return $this->cellSides[$index][$side] && match ($side) {
            0 => $firstRow === $row,
            1 => $firstColumn + $columns - 1 === $column,
            2 => $firstRow + $rows - 1 === $row,
            default => $firstColumn === $column,
        };
    }
}
