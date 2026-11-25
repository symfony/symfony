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

/** @internal */
final class ColumnGeometry
{
    /** @return array{int, float} */
    public static function resolve(float $room, ?int $count, ?float $width, float $gap): array
    {
        $room = max(0.0, $room);
        $gap = max(0.0, $gap);
        $fit = null !== $width && $width + $gap > 0 ? max(1, (int) floor(($room + $gap) / ($width + $gap))) : null;
        $count = max(1, min(100, $fit ?? $count ?? 1, $count ?? $fit ?? 1));

        return [$count, max(0.0, ($room - $gap * ($count - 1)) / $count)];
    }

    /**
     * The old column placement algorithm operates on legal break positions.
     * Geometry supplies those positions in document units rather than rows.
     *
     * @param list<float> $breaks
     * @param list<float> $forced
     *
     * @return list<array{float, float}>
     */
    public static function partition(float $total, int $count, array $breaks, array $forced = [], ?float $fill = null, bool $overflow = false): array
    {
        if ($total <= 0) {
            return [];
        }
        sort($breaks, \SORT_NUMERIC);
        sort($forced, \SORT_NUMERIC);
        $place = static function (float $height) use ($total, $count, $breaks, $forced, $overflow): ?array {
            $parts = [];
            $from = 0.0;
            for ($column = 0; $from < $total - 1e-9; ++$column) {
                if (!$overflow && $column >= $count) {
                    return null;
                }
                $limit = min($total, $from + $height);
                $end = null;
                if ($overflow || $column < $count - 1) {
                    foreach ($forced as $at) {
                        if ($at > $from + 1e-9 && $at <= $limit + 1e-9 && $at < $total) {
                            $end = $at;
                            break;
                        }
                    }
                }
                if (null === $end) {
                    if ($limit >= $total - 1e-9) {
                        $end = $total;
                    } elseif ($overflow || $column < $count - 1) {
                        foreach ($breaks as $at) {
                            if ($at > $limit + 1e-9) {
                                break;
                            }
                            if ($at > $from + 1e-9) {
                                $end = $at;
                            }
                        }
                    }
                }
                if (null === $end && $overflow) {
                    foreach ($breaks as $at) {
                        if ($at > $from + 1e-9) {
                            $end = $at;
                            break;
                        }
                    }
                }
                if (null === $end) {
                    return null;
                }
                $parts[] = [$from, $end];
                $from = $end;
            }

            return $parts;
        };
        $low = max(0.0, $fill ?? $total / $count);
        if (null !== $parts = $place($low)) {
            return $parts;
        }
        $high = max($total, $low);
        $parts = $place($high);
        for ($step = 0; $step < 48; ++$step) {
            $middle = ($low + $high) / 2;
            if (null !== $candidate = $place($middle)) {
                $high = $middle;
                $parts = $candidate;
            } else {
                $low = $middle;
            }
        }

        return $parts;
    }
}
