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
final class FlexGeometry
{
    /**
     * @param list<array{basis: float, minimum: float, maximum: ?float, grow: float, shrink: float}> $items
     *
     * @return list<float>
     */
    public static function resolve(array $items, float $room, float $gap): array
    {
        $room -= $gap * max(0, \count($items) - 1);
        $sizes = array_map(static fn (array $item): float => max($item['minimum'], min($item['basis'], $item['maximum'] ?? \INF)), $items);
        $growing = array_sum($sizes) < $room;
        $remaining = [];
        $initialFree = $room;
        foreach ($items as $index => $item) {
            $flexible = ($growing ? $item['grow'] : $item['shrink']) > 0 && ($growing ? $item['basis'] <= $sizes[$index] : $item['basis'] >= $sizes[$index]);
            if ($flexible) {
                $remaining[] = $index;
            }
            $initialFree -= $flexible ? $item['basis'] : $sizes[$index];
        }
        while ($remaining) {
            foreach ($remaining as $index) {
                $sizes[$index] = $items[$index]['basis'];
            }
            $free = $room - array_sum($sizes);
            $weights = [];
            foreach ($remaining as $index) {
                $weights[$index] = $growing ? $items[$index]['grow'] : $items[$index]['shrink'] * $items[$index]['basis'];
            }
            $total = array_sum($weights);
            if ($total <= 0 || abs($free) < 1.0E-9) {
                break;
            }
            if ($growing && $total < 1) {
                $free = min($free, $initialFree * $total);
            }
            $frozen = [];
            foreach ($remaining as $index) {
                $share = $sizes[$index] + $free * $weights[$index] / $total;
                $clamped = max($items[$index]['minimum'], min($share, $items[$index]['maximum'] ?? \INF));
                $sizes[$index] = max(0.0, $clamped);
                if ($share !== $clamped || 0.0 === $weights[$index]) {
                    $frozen[] = $index;
                }
            }
            if (!$frozen) {
                break;
            }
            $remaining = array_values(array_diff($remaining, $frozen));
        }

        return $sizes;
    }

    /**
     * @param list<array{bool, bool}> $autoMargins
     *
     * @return list<float>
     */
    public static function spaces(int $count, float $free, float $gap, string $justify, array $autoMargins = []): array
    {
        $spaces = array_fill(0, $count + 1, $gap);
        $spaces[0] = $spaces[$count] = 0.0;
        if ($free <= 0 || $count < 1) {
            return $spaces;
        }
        $weights = array_fill(0, $count + 1, 0.0);
        foreach ($autoMargins as $index => [$before, $after]) {
            $weights[$index] += (int) $before;
            $weights[$index + 1] += (int) $after;
        }
        if (array_sum($weights) <= 0) {
            $weights = match ($justify) {
                'center' => [1.0, ...array_fill(0, $count - 1, 0.0), 1.0],
                'end', 'flex-end', 'right' => [1.0, ...array_fill(0, $count, 0.0)],
                'space-between' => $count < 2 ? $weights : [0.0, ...array_fill(0, $count - 1, 1.0), 0.0],
                'space-around' => [1.0, ...array_fill(0, $count - 1, 2.0), 1.0],
                'space-evenly' => array_fill(0, $count + 1, 1.0),
                default => $weights,
            };
        }
        $total = array_sum($weights);
        if ($total > 0) {
            foreach ($weights as $index => $weight) {
                $spaces[$index] += $free * $weight / $total;
            }
        }

        return $spaces;
    }
}
