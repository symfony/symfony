<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Vector;

/** @internal */
final class Geometry
{
    private const MAX_POINTS = 20000;
    private const MAX_LENGTH = 100000.0;
    private const IDENTITY = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];

    /**
     * The subpaths of path data, its curves and arcs flattened into lines; drawn up to the first error, like SVG.
     *
     * @param-out list<list<int>> $vertices
     *
     * @param list<list<int>>|null $vertices Set to the indexes of the points of each subpath that end a command, where markers go
     *
     * @return list<array{points: list<array{float, float}>, closed: bool}>
     */
    public static function path(string $data, int $budget = self::MAX_POINTS, ?array &$vertices = null): array
    {
        $vertices = [];
        $marks = [];
        preg_match_all('/([MmLlHhVvCcSsQqTtAaZz])|([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)/', $data, $tokens, \PREG_SET_ORDER);
        $subpaths = [];
        $points = [];
        $x = $y = $startX = $startY = 0.0;
        // The control point of the last curve, for the smooth ones
        $control = null;
        $command = null;
        $index = 0;
        $count = \count($tokens);
        $number = static function () use (&$index, &$tokens, $count): ?float {
            if ($index < $count && '' === ($tokens[$index][1] ?? '') && isset($tokens[$index][2])) {
                return max(-self::MAX_LENGTH, min(self::MAX_LENGTH, (float) $tokens[$index++][2]));
            }

            return null;
        };
        $flag = static function () use (&$index, &$tokens, $count): ?bool {
            // Flags may be written without a separator, like a 0 0 1 1 1 as "a0 011 1"
            if ($index >= $count || '' !== ($tokens[$index][1] ?? '') || !isset($tokens[$index][2])) {
                return null;
            }
            $token = &$tokens[$index][2];
            if ('0' === $token[0] || '1' === $token[0]) {
                $value = '1' === $token[0];
                $token = substr($token, 1);
                if ('' === $token) {
                    ++$index;
                }

                return $value;
            }

            return null;
        };
        $close = static function (bool $closed, array &$points, array &$marks) use (&$subpaths, &$kept, &$vertices): void {
            if (\count($points) > 1) {
                $subpaths[] = ['points' => $points, 'closed' => $closed];
                $vertices[] = $marks;
                $kept += \count($points);
            }
            $points = [];
            $marks = [];
        };
        $kept = 0;
        while ($index < $count) {
            // At most that many points, the rest of the path left out
            if ($kept + \count($points) >= $budget) {
                break;
            }
            if ('' !== ($tokens[$index][1] ?? '')) {
                $command = $tokens[$index++][1];
                // A path starts with a moveto, else it is in error from the start
                if (0 === $index - 1 && 'M' !== strtoupper($command)) {
                    break;
                }
            } elseif (null === $command) {
                break;
            }
            $relative = ctype_lower($command);
            $dx = $relative ? $x : 0.0;
            $dy = $relative ? $y : 0.0;
            switch (strtoupper($command)) {
                case 'Z':
                    $close(true, $points, $marks);
                    [$x, $y] = [$startX, $startY];
                    $control = null;
                    // Another command follows; numbers after Z are an error
                    $command = null;
                    continue 2;
                case 'M':
                    if (null === ($px = $number()) || null === $py = $number()) {
                        break 2;
                    }
                    $close(false, $points, $marks);
                    [$x, $y] = [$startX, $startY] = [$px + $dx, $py + $dy];
                    $points = [[$x, $y]];
                    $marks = [0];
                    // Pairs after a moveto are linetos
                    $command = $relative ? 'l' : 'L';
                    $control = null;
                    continue 2;
                case 'L':
                    if (null === ($px = $number()) || null === $py = $number()) {
                        break 2;
                    }
                    [$x, $y] = [$px + $dx, $py + $dy];
                    $control = null;
                    break;
                case 'H':
                    if (null === $px = $number()) {
                        break 2;
                    }
                    $x = $px + $dx;
                    $control = null;
                    break;
                case 'V':
                    if (null === $py = $number()) {
                        break 2;
                    }
                    $y = $py + $dy;
                    $control = null;
                    break;
                case 'C':
                case 'S':
                case 'Q':
                case 'T':
                    $upper = strtoupper($command);
                    $values = [];
                    foreach (range(1, match ($upper) {
                        'C' => 6,
                        'S', 'Q' => 4,
                        default => 2,
                    }) as $ignored) {
                        if (null === $value = $number()) {
                            break 3;
                        }
                        $values[] = $value;
                    }
                    $smooth = 'S' === $upper || 'T' === $upper;
                    $q = [$x, $y];
                    // The reflection of the last control point, when the last curve is of the same kind
                    $reflected = null !== $control && $control[2] === ('S' === $upper || 'C' === $upper ? 'cubic' : 'quadratic') ? [2 * $x - $control[0], 2 * $y - $control[1]] : [$x, $y];
                    if ('C' === $upper) {
                        [$c1, $c2, $end] = [[$values[0] + $dx, $values[1] + $dy], [$values[2] + $dx, $values[3] + $dy], [$values[4] + $dx, $values[5] + $dy]];
                    } elseif ('S' === $upper) {
                        [$c1, $c2, $end] = [$reflected, [$values[0] + $dx, $values[1] + $dy], [$values[2] + $dx, $values[3] + $dy]];
                    } else {
                        $q = 'Q' === $upper ? [$values[0] + $dx, $values[1] + $dy] : $reflected;
                        $end = $smooth ? [$values[0] + $dx, $values[1] + $dy] : [$values[2] + $dx, $values[3] + $dy];
                        // A quadratic curve as a cubic one
                        $c1 = [$x + 2 / 3 * ($q[0] - $x), $y + 2 / 3 * ($q[1] - $y)];
                        $c2 = [$end[0] + 2 / 3 * ($q[0] - $end[0]), $end[1] + 2 / 3 * ($q[1] - $end[1])];
                    }
                    if (!$points) {
                        $points[] = [$x, $y];
                        $marks = [0];
                    }
                    foreach (self::cubic([$x, $y], $c1, $c2, $end) as $point) {
                        $points[] = $point;
                    }
                    $marks[] = \count($points) - 1;
                    [$x, $y] = $end;
                    $control = 'C' === $upper || 'S' === $upper ? [$c2[0], $c2[1], 'cubic'] : [$q[0], $q[1], 'quadratic'];
                    continue 2;
                case 'A':
                    if (null === ($rx = $number()) || null === ($ry = $number()) || null === ($rotation = $number()) || null === ($large = $flag()) || null === ($sweep = $flag()) || null === ($px = $number()) || null === $py = $number()) {
                        break 2;
                    }
                    if (!$points) {
                        $points[] = [$x, $y];
                        $marks = [0];
                    }
                    foreach (self::arc([$x, $y], abs($rx), abs($ry), $rotation, $large, $sweep, [$px + $dx, $py + $dy]) as $point) {
                        $points[] = $point;
                    }
                    $marks[] = \count($points) - 1;
                    [$x, $y] = [$px + $dx, $py + $dy];
                    $control = null;
                    continue 2;
            }
            if (!$points) {
                $points[] = [$startX, $startY];
                $marks = [0];
            }
            $points[] = [$x, $y];
            $marks[] = \count($points) - 1;
        }
        $close(false, $points, $marks);

        return $subpaths;
    }

    /**
     * The matrix of a transform attribute: matrix(), translate(), scale(), rotate(), skewX() and skewY(), one after
     * the other; the identity when invalid.
     *
     * @return array{float, float, float, float, float, float}
     */
    public static function transform(string $value): array
    {
        $matrix = self::IDENTITY;
        if (!preg_match_all('/(matrix|translate|scale|rotate|skewx|skewy)\s*\(([^)]*)\)/i', $value, $functions, \PREG_SET_ORDER)) {
            return $matrix;
        }
        foreach ($functions as [, $name, $arguments]) {
            $n = self::numbers($arguments);
            $step = match (strtolower($name)) {
                'matrix' => 6 === \count($n) ? [$n[0], $n[1], $n[2], $n[3], $n[4], $n[5]] : null,
                'translate' => isset($n[0]) ? [1.0, 0.0, 0.0, 1.0, $n[0], $n[1] ?? 0.0] : null,
                'scale' => isset($n[0]) ? [$n[0], 0.0, 0.0, $n[1] ?? $n[0], 0.0, 0.0] : null,
                'rotate' => isset($n[0]) ? self::rotation($n[0], $n[1] ?? 0.0, $n[2] ?? 0.0) : null,
                'skewx' => isset($n[0]) ? [1.0, 0.0, tan(deg2rad($n[0])), 1.0, 0.0, 0.0] : null,
                'skewy' => isset($n[0]) ? [1.0, tan(deg2rad($n[0])), 0.0, 1.0, 0.0, 0.0] : null,
                default => null,
            };
            if (null === $step) {
                return self::IDENTITY;
            }
            $matrix = self::multiply($matrix, $step);
        }

        return $matrix;
    }

    /**
     * @param array{float, float, float, float, float, float} $m
     * @param array{float, float, float, float, float, float} $n
     *
     * @return array{float, float, float, float, float, float}
     */
    public static function multiply(array $m, array $n): array
    {
        return [$m[0] * $n[0] + $m[2] * $n[1], $m[1] * $n[0] + $m[3] * $n[1], $m[0] * $n[2] + $m[2] * $n[3], $m[1] * $n[2] + $m[3] * $n[3], $m[0] * $n[4] + $m[2] * $n[5] + $m[4], $m[1] * $n[4] + $m[3] * $n[5] + $m[5]];
    }

    /**
     * @return list<float>
     */
    public static function numbers(string $value): array
    {
        preg_match_all('/[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/', $value, $m);

        return array_map(static fn (string $number) => max(-self::MAX_LENGTH, min(self::MAX_LENGTH, (float) $number)), $m[0]);
    }

    /**
     * A cubic Bézier curve as points, its start excluded.
     *
     * @param array{float, float} $p0
     * @param array{float, float} $p1
     * @param array{float, float} $p2
     * @param array{float, float} $p3
     *
     * @return list<array{float, float}>
     */
    private static function cubic(array $p0, array $p1, array $p2, array $p3): array
    {
        $length = hypot($p1[0] - $p0[0], $p1[1] - $p0[1]) + hypot($p2[0] - $p1[0], $p2[1] - $p1[1]) + hypot($p3[0] - $p2[0], $p3[1] - $p2[1]);
        $steps = max(4, min(64, (int) ceil($length / 2)));
        $points = [];
        for ($i = 1; $i <= $steps; ++$i) {
            $t = $i / $steps;
            $u = 1 - $t;
            $points[] = [$u ** 3 * $p0[0] + 3 * $u ** 2 * $t * $p1[0] + 3 * $u * $t ** 2 * $p2[0] + $t ** 3 * $p3[0], $u ** 3 * $p0[1] + 3 * $u ** 2 * $t * $p1[1] + 3 * $u * $t ** 2 * $p2[1] + $t ** 3 * $p3[1]];
        }

        return $points;
    }

    /**
     * An elliptical arc as points, its start excluded, from its endpoint form (SVG 2, appendix B.2.4).
     *
     * @param array{float, float} $from
     * @param array{float, float} $to
     *
     * @return list<array{float, float}>
     */
    private static function arc(array $from, float $rx, float $ry, float $rotation, bool $large, bool $sweep, array $to): array
    {
        if ($from === $to) {
            return [];
        }
        // Radii of no length, or too small to square, are a line
        if (0.0 === $rx ** 2 || 0.0 === $ry ** 2) {
            return [$to];
        }
        $phi = deg2rad(fmod($rotation, 360));
        [$cos, $sin] = [cos($phi), sin($phi)];
        $mx = ($from[0] - $to[0]) / 2;
        $my = ($from[1] - $to[1]) / 2;
        $x1 = $cos * $mx + $sin * $my;
        $y1 = -$sin * $mx + $cos * $my;
        // Radii too small are scaled up
        $lambda = $x1 ** 2 / $rx ** 2 + $y1 ** 2 / $ry ** 2;
        if ($lambda > 1) {
            $rx *= sqrt($lambda);
            $ry *= sqrt($lambda);
        }
        $factor = sqrt(max(0.0, ($rx ** 2 * $ry ** 2 - $rx ** 2 * $y1 ** 2 - $ry ** 2 * $x1 ** 2) / ($rx ** 2 * $y1 ** 2 + $ry ** 2 * $x1 ** 2)));
        if ($large === $sweep) {
            $factor = -$factor;
        }
        $cx1 = $factor * $rx * $y1 / $ry;
        $cy1 = -$factor * $ry * $x1 / $rx;
        $cx = $cos * $cx1 - $sin * $cy1 + ($from[0] + $to[0]) / 2;
        $cy = $sin * $cx1 + $cos * $cy1 + ($from[1] + $to[1]) / 2;
        $angle = static fn (float $ux, float $uy, float $vx, float $vy): float => atan2($ux * $vy - $uy * $vx, $ux * $vx + $uy * $vy);
        $start = $angle(1, 0, ($x1 - $cx1) / $rx, ($y1 - $cy1) / $ry);
        $delta = $angle(($x1 - $cx1) / $rx, ($y1 - $cy1) / $ry, (-$x1 - $cx1) / $rx, (-$y1 - $cy1) / $ry);
        if (!$sweep && $delta > 0) {
            $delta -= 2 * \M_PI;
        } elseif ($sweep && $delta < 0) {
            $delta += 2 * \M_PI;
        }
        $steps = max(4, min(128, (int) ceil(abs($delta) * max($rx, $ry) / 2)));
        $points = [];
        for ($i = 1; $i <= $steps; ++$i) {
            $theta = $start + $delta * $i / $steps;
            $points[] = [$cos * $rx * cos($theta) - $sin * $ry * sin($theta) + $cx, $sin * $rx * cos($theta) + $cos * $ry * sin($theta) + $cy];
        }
        $points[\count($points) - 1] = $to;

        return $points;
    }

    /**
     * @return array{float, float, float, float, float, float}
     */
    private static function rotation(float $degrees, float $cx, float $cy): array
    {
        $angle = deg2rad($degrees);
        $rotate = [cos($angle), sin($angle), -sin($angle), cos($angle), 0.0, 0.0];

        return self::multiply(self::multiply([1.0, 0.0, 0.0, 1.0, $cx, $cy], $rotate), [1.0, 0.0, 0.0, 1.0, -$cx, -$cy]);
    }
}
