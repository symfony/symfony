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
final class ScrollSnapGeometry
{
    public static function mode(string $value, bool $vertical): ?string
    {
        $parts = preg_split('/\s+/', strtolower(trim($value))) ?: [];
        if (['none'] === $parts) {
            return '';
        }
        if (!$parts || \count($parts) > 2 || !\in_array($parts[0], ['x', 'y', 'block', 'inline', 'both'], true) || !\in_array($parts[1] ?? 'proximity', ['mandatory', 'proximity'], true)) {
            return null;
        }

        return \in_array($parts[0], $vertical ? ['y', 'block', 'both'] : ['x', 'inline', 'both'], true) ? $parts[1] ?? 'proximity' : '';
    }

    public static function alignment(string $value, bool $vertical): ?string
    {
        $parts = preg_split('/\s+/', strtolower(trim($value))) ?: [];
        $alignment = $vertical ? $parts[0] ?? '' : $parts[1] ?? $parts[0] ?? '';

        return \in_array($alignment, ['start', 'center', 'end'], true) ? $alignment : null;
    }

    public static function position(string $alignment, float $start, float $size, float $marginBefore, float $marginAfter, float $paddingBefore, float $paddingAfter, float $viewport, float $maximum): float
    {
        $position = match ($alignment) {
            'start' => $start - $marginBefore - $paddingBefore,
            'end' => $start + $size + $marginAfter + $paddingAfter - $viewport,
            default => $start + ($size - $viewport) / 2,
        };

        return max(0.0, min($maximum, $position));
    }

    /** @param list<array{float|int, bool}> $points Resolved positions and whether they stop a passing scroll */
    public static function select(array $points, float $requested, ?float $previous, ?float $proximity = null): float
    {
        if (!$points) {
            return $requested;
        }
        $direction = null === $previous ? 0 : $requested <=> $previous;
        usort($points, static fn (array $a, array $b): int => $direction < 0 ? $b[0] <=> $a[0] : $a[0] <=> $b[0]);
        if (0 !== $direction) {
            foreach ($points as [$position, $always]) {
                if ($always && ($position - $previous) * $direction > 0 && ($requested - $position) * $direction > 0) {
                    return $position;
                }
            }
        }
        $ahead = 0 === $direction ? [] : array_filter($points, static fn (array $point): bool => $point[0] === $requested || ($point[0] - $previous) * $direction > 0);
        $candidates = $ahead ?: $points;
        $snapped = $candidates[array_key_first($candidates)][0];
        foreach ($candidates as [$position]) {
            if (abs($position - $requested) < abs($snapped - $requested)) {
                $snapped = $position;
            }
        }

        return null !== $proximity && abs($snapped - $requested) > $proximity ? $requested : $snapped;
    }
}
