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

/**
 * A background that goes from color to color, like CSS linear-gradient(), radial-gradient() and conic-gradient(),
 * and their repeating forms.
 *
 * Each cell of the box takes the color at its center; cells being about
 * twice as tall as wide, vertical distances count double, so that angles
 * look like they do in a browser.
 *
 * @experimental
 */
final class Gradient
{
    /** @var array<string, list<array{0: Color, 1: float, 2?: float}>> The stops resolved for each length of the gradient line */
    private array $resolved = [];

    /**
     * The colors already sampled and painted, by where they are on the gradient line, a box of thousands of rows of a
     * diagonal gradient computing each color once.
     *
     * @var array<string, array{Color, float}|Color|null>
     */
    private array $memo = [];

    /**
     * The color last painted over and its key, the same for every cell of a box: held, never another of its id.
     *
     * @var array{?Color, string}
     */
    private array $lastUnder = [null, ''];

    /**
     * What the places of the cells of a box depend on, by its size.
     *
     * @var array<string, array{float, float, float, float, float, list<array{0: Color, 1: float, 2?: float}>, bool, string, float, float}>
     */
    private array $geometry = [];

    /**
     * The size of the box last placed and its geometry.
     *
     * @var array{?int, ?int, ?array{float, float, float, float, float, list<array{0: Color, 1: float, 2?: float}>, bool, string, float, float}}
     */
    private array $lastSize = [null, null, null];

    /**
     * @param 'linear'|'radial'|'conic'                                                                                    $kind
     * @param float                                                                                                        $angle     The direction of a linear gradient, in degrees: 0 goes up, 90 to the right, like CSS
     * @param list<array{0: Color, 1: float, 2?: float}>                                                                   $stops     Each color, where it is, from 0 to 1, in order, and its alpha when it is not opaque
     * @param bool                                                                                                         $repeating Whether the stops repeat past the last one and before the first one, like repeating-linear-gradient()
     * @param float                                                                                                        $from      Where a conic gradient starts, in degrees clockwise from the top, like conic-gradient(from 90deg, ...)
     * @param list<array{0: Color, 1: ?float, 2: float, 3?: float}>|null                                                   $rawStops  Stops with positions in cells: each color, its share of the gradient line (null when not given), cells added to it and its alpha; resolved for the length of the line when painted, over $stops
     * @param bool                                                                                                         $circle    Whether a radial gradient is a circle, not an ellipse
     * @param 'closest-side'|'closest-corner'|'farthest-side'|'farthest-corner'|array{BackgroundLength, ?BackgroundLength} $extent    How far a radial gradient goes: to a side or a corner of the box, or its radii, the vertical one null for a circle
     * @param array{BackgroundLength, BackgroundLength}|null                                                               $center    Where a radial or conic gradient is centered in the box, null for the center
     * @param Color|null                                                                                                   $backdrop  What its translucent colors are blended over when the box has no background color, which shows where it is transparent; null when the box has one
     */
    public function __construct(
        public readonly string $kind,
        public readonly float $angle,
        public readonly array $stops,
        public readonly bool $repeating = false,
        public readonly float $from = 0.0,
        public readonly ?array $rawStops = null,
        public readonly bool $circle = false,
        public readonly string|array $extent = 'farthest-corner',
        public readonly ?array $center = null,
        public readonly ?Color $backdrop = null,
    ) {
    }

    /**
     * Whether a color of a stop is not opaque, like transparent.
     */
    public function isTranslucent(): bool
    {
        $index = null !== $this->rawStops ? 3 : 2;

        return array_any($this->rawStops ?? $this->stops, static fn (array $stop) => ($stop[$index] ?? 1.0) < 1.0);
    }

    /**
     * The gradient with its translucent colors blended over a color, the background behind the box.
     */
    public function withBackdrop(?Color $backdrop): self
    {
        return new self($this->kind, $this->angle, $this->stops, $this->repeating, $this->from, $this->rawStops, $this->circle, $this->extent, $this->center, $backdrop);
    }

    /**
     * Whether the colors change only from column to column, like a linear gradient to the right: every row is the same.
     */
    public function isHorizontal(): bool
    {
        return 'linear' === $this->kind && [1.0, 0.0] === array_map(abs(...), $this->direction());
    }

    /**
     * Whether the colors change only from row to row, like a linear gradient to the bottom: every column is the same.
     */
    public function isVertical(): bool
    {
        return 'linear' === $this->kind && [0.0, 1.0] === array_map(abs(...), $this->direction());
    }

    /**
     * The direction of a linear gradient as a step across and down, exact at right angles, so that a horizontal or a
     * vertical gradient does not drift from row to row or from column to column.
     *
     * @return array{float, float}
     */
    private function direction(): array
    {
        $angle = fmod(fmod($this->angle, 360.0) + 360.0, 360.0);

        return match ($angle) {
            0.0 => [0.0, -1.0],
            90.0 => [1.0, 0.0],
            180.0 => [0.0, 1.0],
            270.0 => [-1.0, 0.0],
            default => [sin(deg2rad($this->angle)), -cos(deg2rad($this->angle))],
        };
    }

    /**
     * The color of a cell of a box, its translucent colors blended over the backdrop.
     */
    public function colorAt(int $column, int $row, int $columns, int $rows): Color
    {
        return $this->paintAt($column, $row, $columns, $rows) ?? $this->backdrop ?? Color::hex('#000000');
    }

    /**
     * The color painted in a cell of a box: null where the gradient is transparent, what is under it showing, its
     * translucent colors blended over a color, the background color of the box, or else the backdrop.
     */
    public function paintAt(int $column, int $row, int $columns, int $rows, ?Color $under = null): ?Color
    {
        [$color, $alpha, $key] = $this->sampleKeyed($column, $row, $columns, $rows);
        if ($alpha >= 1.0) {
            return $color;
        }
        if ($alpha < 0.005) {
            return null;
        }
        $over = $under ?? $this->backdrop;
        if ($over !== $this->lastUnder[0]) {
            $this->lastUnder = [$over, (string) $over?->toBackgroundCode()];
        }
        $key .= '>'.$this->lastUnder[1];
        if (\array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        return $this->remember($key, ($under ?? $this->backdrop ?? Color::hex('#000000'))->mix($color, (int) round($alpha * 100)));
    }

    /**
     * Keeps a color sampled or painted, a bounded number of them.
     *
     * @template T of array{Color, float}|Color|null
     *
     * @param T $value
     *
     * @return T
     */
    private function remember(string $key, array|Color|null $value): array|Color|null
    {
        if (\count($this->memo) >= 16_384) {
            $this->memo = [];
        }

        return $this->memo[$key] = $value;
    }

    /**
     * The color of a cell of a box and its alpha, its translucent colors not blended.
     *
     * @return array{Color, float}
     */
    public function sampleAt(int $column, int $row, int $columns, int $rows): array
    {
        [$color, $alpha] = $this->sampleKeyed($column, $row, $columns, $rows);

        return [$color, $alpha];
    }

    /**
     * @return array{Color, float, string} The color of a cell, its alpha, and where it is on the gradient
     */
    private function sampleKeyed(int $column, int $row, int $columns, int $rows): array
    {
        $key = $this->placeAt($column, $row, $columns, $rows, $t, $stops);
        if (\array_key_exists($key, $this->memo)) {
            return [...$this->memo[$key], $key];
        }
        if ($this->repeating) {
            // The stops again and again, before the first one and past the last one
            $first = $stops[0][1];
            $period = $stops[\count($stops) - 1][1] - $first;
            if ($period <= 1e-9) {
                return [$stops[\count($stops) - 1][0], $stops[\count($stops) - 1][2] ?? 1.0, $key];
            }
            $t = $first + fmod(fmod($t - $first, $period) + $period, $period);
        }

        return [...$this->remember($key, self::colorOf($stops, $t)), $key];
    }

    /**
     * Where a cell is on the gradient, as a key, two cells of the same key, in boxes of the same size, having the same
     * color; and where exactly, with the stops of a box of its size.
     *
     * @internal
     *
     * @param-out float                                      $t
     * @param-out list<array{0: Color, 1: float, 2?: float}> $stops
     */
    public function placeAt(int $column, int $row, int $columns, int $rows, ?float &$t = null, ?array &$stops = null): string
    {
        // The cells of a box all have its size: the geometry of the last one held
        if ($columns !== $this->lastSize[0] || $rows !== $this->lastSize[1]) {
            $this->lastSize = [$columns, $rows, $this->geometry[$columns.':'.$rows] ?? $this->geometry($columns, $rows)];
        }
        [$cx, $cy, $a, $b, $length, $stops, $smooth, $suffix, $first, $last] = $this->lastSize[2];
        $x = $column + 0.5 - $cx;
        $y = ($row + 0.5) * 2 - $cy;
        if ('radial' === $this->kind) {
            $t = sqrt(($x / $a) ** 2 + ($y / $b) ** 2);
        } elseif ('conic' === $this->kind) {
            // The angle around the center, clockwise from the top, from where it starts
            $degrees = rad2deg(atan2($x, -$y)) - $this->from;
            $t = fmod(fmod($degrees, 360.0) + 360.0, 360.0) / 360;
        } else {
            $t = $length > 0 ? ($x * $a + $y * $b) / $length + 0.5 : 0.0;
        }

        // Where it is on the line, exactly, the cells of a diagonal at the same place sharing it, a hard stop or a thin
        // stripe on its side; before the first stop or past the last one, the color of that stop, like the cells past the
        // circle of a radial gradient; and for the stops of lengths, the length of the line
        return match (true) {
            $this->repeating => (string) $t,
            $t < $first => 'before',
            $t > $last => 'past',
            // Without a hard stop, closer than a tenth of a step of a color apart: a diagonal of a box thousands of rows tall
            // computing each of its few hundred colors once; as the bytes of the number, faster to make than its digits,
            // and as many, one for each of these round numbers
            $smooth => pack('e', round($t, 4)),
            default => (string) $t,
        }.$suffix;
    }

    /**
     * What the places of the cells of a box of a size depend on, computed once for each size: its center, the radii of a
     * radial gradient or the direction of a linear one, the length of its line, its stops and whether they are smooth.
     *
     * @return array{float, float, float, float, float, list<array{0: Color, 1: float, 2?: float}>, bool, string, float, float}
     */
    private function geometry(int $columns, int $rows): array
    {
        // Rows count double, cells being twice as tall as wide; radial and conic gradients go from their center
        [$width, $height] = [(float) $columns, $rows * 2.0];
        [$cx, $cy] = null !== $this->center && 'linear' !== $this->kind ? [$this->offset($this->center[0], $width, 1), $this->offset($this->center[1], $height, 2)] : [$width / 2, $height / 2];
        [$a, $b] = [0.0, 0.0];
        if ('radial' === $this->kind) {
            [$a, $b] = $this->radii($cx, $cy, $width, $height);
            $length = $a;
        } elseif ('conic' === $this->kind) {
            $length = 0.0;
        } else {
            [$a, $b] = $this->direction();
            // The gradient line goes from corner to corner along the direction
            $length = abs($columns * $a) + abs($rows * 2 * $b);
        }
        $stops = null !== $this->rawStops ? ($this->resolved[(string) round($length, 3)] ??= self::resolve($this->rawStops, $length)) : $this->stops;
        if (\count($this->geometry) >= 256) {
            $this->geometry = [];
        }

        return $this->geometry[$columns.':'.$rows] = [$cx, $cy, $a, $b, $length, $stops, self::smooth($stops), '@'.(null !== $this->rawStops ? round($length, 3) : ''), $stops[0][1] ?? 0.0, $stops[\count($stops) - 1][1] ?? 0.0];
    }

    /**
     * A length of the box, in columns or half rows: a share of its size plus cells, a row being two half rows.
     */
    private function offset(BackgroundLength $length, float $size, int $perCell): float
    {
        $offset = $size * $length->percent / 100 + $length->cells * $perCell;

        return $length->fromEnd ? $size - $offset : $offset;
    }

    /**
     * The horizontal and vertical radii of a radial gradient centered at a point of a box, in columns and half rows.
     *
     * @return array{float, float}
     */
    private function radii(float $cx, float $cy, float $width, float $height): array
    {
        if (\is_array($this->extent)) {
            $rx = $this->offset($this->extent[0], $width, 1);
            $ry = null !== $this->extent[1] ? $this->offset($this->extent[1], $height, 2) : $rx;

            return [max(0.5, $rx), max(0.5, $ry)];
        }
        // The distances to the sides of the box, the closest or the farthest of each axis
        $pick = str_starts_with($this->extent, 'closest') ? min(...) : max(...);
        [$sx, $sy] = [$pick($cx, $width - $cx), $pick($cy, $height - $cy)];
        if ($this->circle) {
            $r = match ($this->extent) {
                'closest-side' => min($sx, $sy),
                'farthest-side' => max($sx, $sy),
                default => hypot($sx, $sy),
            };

            return [max(0.5, $r), max(0.5, $r)];
        }
        // An ellipse through a corner keeps the proportions of the one touching the sides
        $factor = str_ends_with($this->extent, 'corner') ? \M_SQRT2 : 1.0;

        return [max(0.5, $sx * $factor), max(0.5, $sy * $factor)];
    }

    /**
     * The color at a point of the gradient line, from 0 to 1.
     */
    public function at(float $t): Color
    {
        [$color, $alpha] = self::colorOf($this->stops, $t);

        return $alpha < 1.0 ? ($this->backdrop ?? Color::hex('#000000'))->mix($color, (int) round($alpha * 100)) : $color;
    }

    /**
     * The positions of stops on a gradient line of a length, in cells: like CSS, the first one at 0 and the last one
     * at 1 when not given, the others spread between their neighbors, and none before the one before it.
     *
     * @param list<array{0: Color, 1: ?float, 2: float, 3?: float}> $stops Each color, its share of the line (null when not given), cells added to it and its alpha
     *
     * @return list<array{0: Color, 1: float, 2?: float}>
     */
    public static function resolve(array $stops, float $length): array
    {
        $at = array_map(static fn (array $stop) => null === $stop[1] ? null : $stop[1] + ($length > 0 ? $stop[2] / $length : 0.0), $stops);
        $count = \count($at);
        if (0 === $count) {
            return [];
        }
        $at[0] ??= 0.0;
        $at[$count - 1] ??= 1.0;
        for ($i = 1; $i < $count; ++$i) {
            if (null === $at[$i]) {
                for ($next = $i; null === $at[$next]; ++$next) {
                }
                $from = (float) $at[$i - 1];
                for ($j = $i; $j < $next; ++$j) {
                    $at[$j] = $from + ((float) $at[$next] - $from) * ($j - $i + 1) / ($next - $i + 1);
                }
            }
            $at[$i] = max((float) $at[$i - 1], (float) $at[$i]);
        }

        return array_map(static fn (array $stop, ?float $position) => isset($stop[3]) && $stop[3] < 1.0 ? [$stop[0], (float) $position, $stop[3]] : [$stop[0], (float) $position], $stops, $at);
    }

    /**
     * @param list<array{0: Color, 1: float, 2?: float}> $stops
     *
     * @return array{Color, float} The color and its alpha
     */
    /**
     * Whether the stops of a gradient change its color smoothly: a quarter of its line apart at least, a step of a color
     * longer than ten times the 1e-4 of its line its places are rounded to, no two of them at the same place, like a
     * hard stop.
     *
     * @param list<array{0: Color, 1: float, 2?: float}> $stops
     */
    private static function smooth(array $stops): bool
    {
        for ($i = 1, $count = \count($stops); $i < $count; ++$i) {
            if ($stops[$i][1] - $stops[$i - 1][1] < 0.26) {
                return false;
            }
        }

        return true;
    }

    private static function colorOf(array $stops, float $t): array
    {
        $first = $stops[0];
        if ($t <= $first[1] || 1 === \count($stops)) {
            return [$first[0], $first[2] ?? 1.0];
        }
        foreach ($stops as $i => $stop) {
            [$color, $position] = $stop;
            if ($t <= $position) {
                $previous = $stops[$i - 1];
                [$before, $from] = $previous;
                $span = $position - $from;
                if ($span <= 0) {
                    return [$color, $stop[2] ?? 1.0];
                }
                $f = ($t - $from) / $span;
                $a1 = $previous[2] ?? 1.0;
                $a2 = $stop[2] ?? 1.0;
                if ($a1 >= 1.0 && $a2 >= 1.0) {
                    return [$before->mix($color, (int) round($f * 100)), 1.0];
                }
                // Premultiplied, like CSS: a transparent stop fades the alpha, not the color, of the other one
                $alpha = (1 - $f) * $a1 + $f * $a2;
                if ($alpha <= 0.0) {
                    return [$color, 0.0];
                }
                [$c1, $c2] = [$before->toRgb(), $color->toRgb()];
                $channel = static fn (string $c): int => (int) round(((1 - $f) * $a1 * $c1[$c] + $f * $a2 * $c2[$c]) / $alpha);

                return [Color::rgb($channel('r'), $channel('g'), $channel('b')), $alpha];
            }
        }
        $last = $stops[\count($stops) - 1];

        return [$last[0], $last[2] ?? 1.0];
    }
}
