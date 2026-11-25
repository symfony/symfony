<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Animation;

/** @internal */
final class Easing
{
    private const BEZIERS = ['ease' => [0.25, 0.1, 0.25, 1.0], 'ease-in' => [0.42, 0.0, 1.0, 1.0], 'ease-out' => [0.0, 0.0, 0.58, 1.0], 'ease-in-out' => [0.42, 0.0, 0.58, 1.0]];

    public static function evaluate(array $timing, float $t): float
    {
        [$function, $steps, $position] = $timing;
        $t = match ($timing[0]) {
            // Like steps(): jump-start jumps at the start of each step, jump-end at its end, jump-none holds the first and last
            // values for a step each, jump-both jumps at both ends
            'steps' => min(1.0, match ($position) {
                'start' => ceil($t * $steps) / $steps,
                'none' => floor($t * $steps) / ($steps - 1),
                'both' => (floor($t * $steps) + 1) / ($steps + 1),
                default => floor($t * $steps) / $steps,
            }),
            'linear' => isset($timing['points']) ? self::piecewise($timing['points'], $t) : $t,
            default => self::bezier($timing[3] ?? self::BEZIERS[$function] ?? self::BEZIERS['ease'], $t),
        };

        return $t;
    }

    public static function parse(string $value): ?array
    {
        $value = strtolower(trim($value));

        return match (true) {
            'linear' === $value => ['linear', 0, 'end'],
            isset(self::BEZIERS[$value]) => [$value, 0, 'end'],
            'step-start' === $value => ['steps', 1, 'start'],
            'step-end' === $value => ['steps', 1, 'end'],
            // jump-none needs two steps at least: one would never change
            1 === preg_match('/^steps\(\s*(\d{1,4})\s*(?:,\s*(jump-start|start|jump-end|end|jump-none|jump-both))?\s*\)$/', $value, $m) && (int) $m[1] > ('jump-none' === ($m[2] ?? '') ? 1 : 0) => ['steps', (int) $m[1], match ($m[2] ?? 'end') {
                'start', 'jump-start' => 'start',
                'jump-none' => 'none',
                'jump-both' => 'both',
                default => 'end',
            }],
            1 === preg_match('/^cubic-bezier\((.*)\)$/s', $value, $m) => self::cubicBezier($m[1]),
            1 === preg_match('/^linear\((.*)\)$/s', $value, $m) => self::linearStops($m[1]),
            default => null,
        };
    }

    /**
     * @return array{'cubic-bezier', int, 'end', list<float>}|null
     */
    private static function cubicBezier(string $arguments): ?array
    {
        $points = \Symfony\Component\HtmlRenderer\Css\Selector::splitTopLevel($arguments);
        if (4 !== \count($points) || !array_all($points, static fn (string $p) => is_numeric($p))) {
            return null;
        }
        $points = array_map(floatval(...), $points);
        // The abscissas are times: they stay within the iteration
        if ($points[0] < 0 || $points[0] > 1 || $points[2] < 0 || $points[2] > 1) {
            return null;
        }

        return ['cubic-bezier', 0, 'end', $points];
    }

    /**
     * The points of linear(): each stop an output, at one or two input percentages, the missing inputs spread evenly.
     *
     * @return array{0: 'linear', 1: int, 2: 'end', points: list<array{float, float}>}|null
     */
    private static function linearStops(string $arguments): ?array
    {
        $stops = [];
        foreach (\Symfony\Component\HtmlRenderer\Css\Selector::splitTopLevel($arguments) as $stop) {
            $parts = \Symfony\Component\HtmlRenderer\Css\ValueTokenizer::split($stop);
            if (!$parts || \count($parts) > 3 || !is_numeric($parts[0])) {
                return null;
            }
            $inputs = [];
            foreach (\array_slice($parts, 1) as $part) {
                if (!preg_match('/^(-?\d*\.?\d+)%$/', $part, $m)) {
                    return null;
                }
                $inputs[] = (float) $m[1] / 100;
            }
            foreach ($inputs ?: [null] as $input) {
                $stops[] = [$input, (float) $parts[0]];
            }
        }
        if (\count($stops) < 2) {
            return null;
        }
        $last = \count($stops) - 1;
        $stops[0][0] ??= 0.0;
        $stops[$last][0] ??= 1.0;
        // An input is never before the ones of the stops before it
        $largest = $stops[0][0];
        foreach ($stops as $i => [$input]) {
            if (null !== $input) {
                $stops[$i][0] = $largest = max($largest, $input);
            }
        }
        // Runs of missing inputs are spread evenly between their neighbors
        for ($i = 1; $i < $last; ++$i) {
            if (null !== $stops[$i][0]) {
                continue;
            }
            $end = $i;
            while (null === $stops[$end][0]) {
                ++$end;
            }
            $from = $stops[$i - 1][0];
            for ($j = $i; $j < $end; ++$j) {
                $stops[$j][0] = $from + ($stops[$end][0] - $from) * ($j - $i + 1) / ($end - $i + 1);
            }
            $i = $end;
        }

        return ['linear', 0, 'end', 'points' => $stops];
    }

    /**
     * The output of a cubic Bézier curve at an input, solved by Newton's method, bisection when it does not converge.
     *
     * @param list<float> $points x1, y1, x2, y2
     */
    private static function bezier(array $points, float $x): float
    {
        [$x1, $y1, $x2, $y2] = $points;
        $curve = static fn (float $t, float $p1, float $p2): float => ((1 - 3 * $p2 + 3 * $p1) * $t + (3 * $p2 - 6 * $p1)) * $t * $t + 3 * $p1 * $t;
        if ($x <= 0.0 || $x >= 1.0) {
            // Outside of the iteration, the curve goes on along its end tangents
            $slope = $x <= 0.0 ? $x1 > 0 ? $y1 / $x1 : ($x2 > 0 ? $y2 / $x2 : 0.0) : ($x2 < 1 ? (1 - $y2) / (1 - $x2) : ($x1 < 1 ? (1 - $y1) / (1 - $x1) : 0.0));

            return $x <= 0.0 ? $slope * $x : 1 + $slope * ($x - 1);
        }
        $t = $x;
        for ($i = 0; $i < 8; ++$i) {
            $error = $curve($t, $x1, $x2) - $x;
            if (abs($error) < 1.0E-7) {
                return $curve($t, $y1, $y2);
            }
            $derivative = (3 * (1 - 3 * $x2 + 3 * $x1) * $t + 2 * (3 * $x2 - 6 * $x1)) * $t + 3 * $x1;
            if (abs($derivative) < 1.0E-6) {
                break;
            }
            $t -= $error / $derivative;
        }
        $low = 0.0;
        $high = 1.0;
        $t = $x;
        for ($i = 0; $i < 64 && $high - $low > 1.0E-7; ++$i) {
            $t = ($low + $high) / 2;
            if ($curve($t, $x1, $x2) < $x) {
                $low = $t;
            } else {
                $high = $t;
            }
        }

        return $curve($t, $y1, $y2);
    }

    /**
     * The output of linear() at an input, along the line of the two points around it, or of the first or last two.
     *
     * @param list<array{float, float}> $points
     */
    private static function piecewise(array $points, float $x): float
    {
        $count = \count($points);
        $segment = $x < $points[0][0] ? 0 : $count - 2;
        for ($i = 0; $i < $count - 1; ++$i) {
            if ($x >= $points[$i][0] && $x <= $points[$i + 1][0]) {
                // The last point at an input wins
                $segment = $i;
                if ($x < $points[$i + 1][0]) {
                    break;
                }
            }
        }
        [[$x0, $y0], [$x1, $y1]] = [$points[$segment], $points[$segment + 1]];

        return $x1 === $x0 ? $y1 : $y0 + ($y1 - $y0) * ($x - $x0) / ($x1 - $x0);
    }
}
