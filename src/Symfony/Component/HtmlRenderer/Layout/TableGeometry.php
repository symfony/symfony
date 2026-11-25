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
 * @internal
 */
final class TableGeometry
{
    /**
     * Specified columns keep their sizes; unspecified columns share the remaining space.
     *
     * @param array<int, float|int> $given
     *
     * @return list<float>
     */
    public static function fixedColumns(int $count, float $budget, array $given): array
    {
        $free = $count - \count($given);
        $left = max(0.0, $budget - array_sum($given));
        $total = array_sum($given);
        $widths = [];
        for ($column = 0; $column < $count; ++$column) {
            if (isset($given[$column])) {
                $widths[] = (float) $given[$column] + (0 === $free ? ($total > 0 ? $left * $given[$column] / $total : $left / $count) : 0.0);
            } else {
                $widths[] = $left / $free;
            }
        }

        return $widths;
    }

    /**
     * @param list<array{column: int, span: int, width: float, minimum: float}> $cells
     *
     * @return list<float>
     */
    public static function columns(array $cells, int $count, float $budget, float $gap, bool $stretch, array $specified = []): array
    {
        $widths = $floors = array_fill(0, $count, 0.0);
        foreach ($cells as $cell) {
            if (1 === $cell['span']) {
                $widths[$cell['column']] = max($widths[$cell['column']], $cell['width']);
                $floors[$cell['column']] = max($floors[$cell['column']], $cell['minimum']);
            }
        }
        foreach ($cells as $cell) {
            $column = $cell['column'];
            $span = $cell['span'];
            $missing = $cell['width'] - array_sum(\array_slice($widths, $column, $span)) - $gap * ($span - 1);
            if ($missing > 0) {
                for ($i = 0; $i < $span; ++$i) {
                    $widths[$column + $i] += $missing / $span;
                }
            }
        }
        foreach ($cells as $cell) {
            $column = $cell['column'];
            $span = $cell['span'];
            foreach (self::floorShares($floors, $widths, $column, $span, $cell['minimum'] - $gap * ($span - 1)) as $index => $share) {
                $floors[$index] += $share;
            }
        }

        $budget = max(0.0, $budget - $gap * max(0, $count - 1));
        $total = array_sum($widths);
        if ($total > $budget) {
            $slack = $total - array_sum($floors);
            if ($slack > 0) {
                $deficit = min($slack, $total - $budget);
                foreach ($widths as $i => &$width) {
                    $width = max($floors[$i], $width - $deficit * ($width - $floors[$i]) / $slack);
                }
                unset($width);
            }
        } elseif ($stretch && $total < $budget) {
            $extra = $budget - $total;
            $growing = array_diff_key($widths, \count($specified) < $count ? $specified : []);
            $total = array_sum($growing);
            foreach ($growing as $index => $value) {
                $widths[$index] += $total > 0 ? $extra * $value / $total : $extra / \count($growing);
            }
        }

        return $widths;
    }

    /**
     * Distributes a spanning minimum within the preferred widths still available.
     *
     * @param list<float|int> $floors
     * @param list<float|int> $widths
     *
     * @return array<int, float> Increments keyed by column, in source order
     */
    public static function floorShares(array $floors, array $widths, int $column, int $span, float $floor): array
    {
        $slacks = [];
        for ($index = $column; $index < $column + $span; ++$index) {
            $slacks[$index] = max(0.0, $widths[$index] - $floors[$index]);
        }
        $total = array_sum($slacks);
        $missing = min($total, $floor - array_sum(\array_slice($floors, $column, $span)));
        if ($missing <= 0 || $total <= 0) {
            return [];
        }
        foreach ($slacks as &$slack) {
            $slack = $missing * $slack / $total;
        }
        unset($slack);

        return $slacks;
    }

    /**
     * @param list<array{row: int, span: int, height: float}> $cells
     *
     * @return list<float>
     */
    public static function rows(array $cells, int $count, float $gap): array
    {
        $heights = array_fill(0, $count, 0.0);
        foreach ($cells as $cell) {
            if (1 === $cell['span']) {
                $heights[$cell['row']] = max($heights[$cell['row']], $cell['height']);
            }
        }

        return self::extendRows($heights, self::orderRowSpans($cells), $gap);
    }

    /**
     * Keep cell identities and equal-span order stable, including when heights are measured afterwards.
     *
     * @param array<int, array{row: int, span: int, height: int|float}> $cells
     *
     * @return array<int, array{row: int, span: int, height: int|float}>
     */
    public static function orderRowSpans(array $cells): array
    {
        uasort($cells, static fn (array $first, array $second): int => $first['span'] <=> $second['span']);

        return $cells;
    }

    /**
     * Grow the last occupied row of each ordered span by its remaining deficit.
     * The minimum applies to occupied geometry, without raising empty rows unless they grow.
     *
     * @param list<int|float>                                           $heights
     * @param array<int, array{row: int, span: int, height: int|float}> $cells   Ordered shortest spans first
     *
     * @return list<int|float>
     */
    public static function extendRows(array $heights, array $cells, float $gap = 0.0, float $minimum = 0.0): array
    {
        foreach ($cells as $cell) {
            $occupied = \array_slice($heights, $cell['row'], $cell['span']);
            if ($minimum > 0) {
                $occupied = array_map(static fn (int|float $height): int|float => max($minimum, $height), $occupied);
            }
            $spanned = array_sum($occupied) + $gap * ($cell['span'] - 1);
            if ($cell['height'] > $spanned) {
                $last = $cell['row'] + $cell['span'] - 1;
                $heights[$last] = max($minimum, $heights[$last]) + ($cell['height'] - $spanned);
            }
        }

        return $heights;
    }
}
