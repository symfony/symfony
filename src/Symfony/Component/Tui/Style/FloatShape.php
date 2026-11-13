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
 * The shape the text beside a float wraps around, like CSS shape-outside: a circle, an ellipse, an inset rectangle
 * with round corners, a polygon, or one of the boxes of the float.
 *
 * Lengths are in columns, and so are the vertical ones: a row is as many columns tall as a cell is taller than wide,
 * so that a circle is round. A length is a float, a percentage of the reference box a string like "50%".
 *
 * @experimental
 */
final class FloatShape
{
    public const BOXES = ['margin-box', 'border-box', 'padding-box', 'content-box'];

    private const EPSILON = 1e-6;

    /**
     * @param 'box'|'circle'|'ellipse'|'inset'|'polygon'|'image' $kind     An image: its pixels more opaque than the threshold, at its size from the top left of the box
     * @param list<float|string>                                 $values   circle: the radius; ellipse: the two radii; inset: the top, right, bottom and left insets;
     *                                                                     polygon: x and y of each point; a radius can be closest-side or farthest-side
     * @param array{float, float, 2?: int, 3?: int}|null         $at       The center of a circle or an ellipse, like object-position: fractions of the box, then cells further
     * @param list<float|string>                                 $radii    The corners of an inset, from the top left: the horizontal radius then the vertical one of each
     * @param float                                              $rowRatio How many columns tall a row is
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $box = 'margin-box',
        public readonly array $values = [],
        public readonly ?array $at = null,
        public readonly array $radii = [],
        public readonly float $rowRatio = 2.0,
        public readonly ?RasterImage $image = null,
    ) {
    }

    /**
     * The columns the shape takes from the side of the float at each of its rows: from its left edge for a float at
     * the left, from its right edge for one at the right; 0 where it takes none, the text beside it there.
     *
     * @param int                       $columns The width of the float, its margins included
     * @param int                       $rows    Its rows, its margins included
     * @param array{int, int, int, int} $margin  The rows and columns of its margin box outside its border box: top, right, bottom, left
     * @param array{int, int, int, int} $border  Those of its border box outside its padding box
     * @param array{int, int, int, int} $padding Those of its padding box outside its content box
     * @param float                     $grow    The shape-margin, in columns
     *
     * @return list<int>
     */
    public function profile(int $columns, int $rows, string $side, array $margin = [0, 0, 0, 0], array $border = [0, 0, 0, 0], array $padding = [0, 0, 0, 0], float $grow = 0.0, float $threshold = 0.0): array
    {
        if ('image' === $this->kind) {
            return $this->imageProfile($columns, $rows, $side, $margin, $border, $padding, $grow, $threshold);
        }
        $k = $this->rowRatio;
        $boxes = array_search($this->box, self::BOXES, true) ?: 0;
        $inset = [0, 0, 0, 0];
        foreach (\array_slice([$margin, $border, $padding], 0, $boxes) as $edges) {
            foreach ($edges as $i => $edge) {
                $inset[$i] += max(0, $edge);
            }
        }
        // The reference box
        $left = (float) $inset[3];
        $top = $inset[0] * $k;
        $width = max(0.0, $columns - $inset[1] - $inset[3]);
        $height = max(0.0, ($rows - $inset[0] - $inset[2]) * $k);
        $grow = max(0.0, $grow);

        $extent = $this->extent($left, $top, $width, $height, $grow);
        $profile = [];
        for ($row = 0; $row < $rows; ++$row) {
            $span = $extent($row * $k + self::EPSILON, ($row + 1) * $k - self::EPSILON);
            if (null === $span) {
                $profile[] = 0;
                continue;
            }
            // Within the margin box, the area the shape is clipped to
            $edge = 'left' === $side ? min((float) $columns, $span[1]) : $columns - max(0.0, $span[0]);
            // A shape of numbers too large to compute takes the box
            if (!is_finite($edge)) {
                $edge = (float) $columns;
            }
            $profile[] = max(0, min($columns, (int) ceil($edge - self::EPSILON)));
        }

        return $profile;
    }

    /**
     * The profile of an image: its pixels more opaque than the threshold, the image at its size (8 CSS pixels a column,
     * 16 a row) from the top left of the box, not repeated, like CSS; the shape-margin around them, row by row.
     *
     * @param array{int, int, int, int} $margin
     * @param array{int, int, int, int} $border
     * @param array{int, int, int, int} $padding
     *
     * @return list<int>
     */
    private function imageProfile(int $columns, int $rows, string $side, array $margin, array $border, array $padding, float $grow, float $threshold): array
    {
        $boxes = array_search($this->box, self::BOXES, true) ?: 0;
        [$top, $left] = [0, 0];
        foreach (\array_slice([$margin, $border, $padding], 0, $boxes) as $edges) {
            $top += max(0, $edges[0]);
            $left += max(0, $edges[3]);
        }
        $size = $this->image?->peekSize();
        if (null === $size) {
            return array_fill(0, $rows, $columns);
        }
        $imageColumns = max(1, min(1000, (int) ceil($size[0] / $this->image->getResolution() / 8)));
        $imageRows = max(1, min(1000, (int) ceil($size[1] / $this->image->getResolution() / 16)));
        $spans = $this->image->opaqueSpans($imageColumns, $imageRows, $threshold);
        // The rows and columns the margin reaches, a row as tall as rowRatio columns
        $reach = (int) ceil(max(0.0, $grow) / $this->rowRatio);
        $wide = (int) ceil(max(0.0, $grow));
        $profile = [];
        for ($row = 0; $row < $rows; ++$row) {
            $span = null;
            for ($at = $row - $top - $reach; $at <= $row - $top + $reach; ++$at) {
                if (null !== $found = $spans[$at] ?? null) {
                    $span = [min($span[0] ?? \PHP_INT_MAX, $found[0]), max($span[1] ?? \PHP_INT_MIN, $found[1])];
                }
            }
            if (null === $span) {
                $profile[] = 0;
                continue;
            }
            $profile[] = max(0, min($columns, 'left' === $side ? $left + $span[1] + 1 + $wide : $columns - ($left + $span[0] - $wide)));
        }

        return $profile;
    }

    /**
     * The function giving the leftmost and rightmost points of the shape between two heights, null where it is not.
     *
     * @return \Closure(float, float): ?array{float, float}
     */
    private function extent(float $left, float $top, float $width, float $height, float $grow): \Closure
    {
        $length = static fn (float|string $value, float $of): float => \is_string($value) ? (float) $value / 100 * $of : $value;

        if ('polygon' === $this->kind) {
            $points = [];
            for ($i = 0; $i + 1 < \count($this->values); $i += 2) {
                $points[] = [$left + $length($this->values[$i], $width), $top + $length($this->values[$i + 1], $height)];
            }

            return static function (float $from, float $to) use ($points, $grow): ?array {
                // The shape-margin around: the band taller and the points further, a bit more than the round edge it adds
                $from -= $grow;
                $to += $grow;
                $xs = [];
                foreach ($points as $i => [$x1, $y1]) {
                    [$x2, $y2] = $points[($i + 1) % \count($points)];
                    if (max($y1, $y2) < $from || min($y1, $y2) > $to) {
                        continue;
                    }
                    if (abs($y2 - $y1) < self::EPSILON) {
                        array_push($xs, $x1, $x2);
                        continue;
                    }
                    foreach ([max($from, min($y1, $y2)), min($to, max($y1, $y2))] as $y) {
                        $xs[] = $x1 + ($x2 - $x1) * ($y - $y1) / ($y2 - $y1);
                    }
                }

                return $xs ? [min($xs) - $grow, max($xs) + $grow] : null;
            };
        }

        // The others are rectangles with round corners: the circle and the ellipse are all corners
        if ('circle' === $this->kind || 'ellipse' === $this->kind) {
            $cx = $left + ($this->at[0] ?? 0.5) * $width + ($this->at[2] ?? 0);
            $cy = $top + ($this->at[1] ?? 0.5) * $height + ($this->at[3] ?? 0) * $this->rowRatio;
            $radius = static fn (float|string $value, float $center, float $from, float $size, float $of): float => match ($value) {
                'closest-side' => max(0.0, min($center - $from, $from + $size - $center)),
                'farthest-side' => max(0.0, $center - $from, $from + $size - $center),
                default => max(0.0, $length($value, $of)),
            };
            if ('circle' === $this->kind) {
                $value = $this->values[0] ?? 'closest-side';
                $r = \in_array($value, ['closest-side', 'farthest-side'], true)
                    ? ('closest-side' === $value ? min($radius($value, $cx, $left, $width, 0), $radius($value, $cy, $top, $height, 0)) : max($radius($value, $cx, $left, $width, 0), $radius($value, $cy, $top, $height, 0)))
                    : $radius($value, 0, 0, 0, sqrt($width ** 2 + $height ** 2) / sqrt(2));
                $rx = $ry = $r;
            } else {
                $rx = $radius($this->values[0] ?? 'closest-side', $cx, $left, $width, $width);
                $ry = $radius($this->values[1] ?? 'closest-side', $cy, $top, $height, $height);
            }
            if ($rx <= 0 || $ry <= 0) {
                return static fn (float $from, float $to): ?array => null;
            }
            $rx += $grow;
            $ry += $grow;

            return self::roundedRectangle($cx - $rx, $cy - $ry, $cx + $rx, $cy + $ry, array_fill(0, 4, [$rx, $ry]));
        }

        $insets = 'inset' === $this->kind ? $this->values : [0.0, 0.0, 0.0, 0.0];
        $x1 = $left + $length($insets[3] ?? 0.0, $width);
        $x2 = $left + $width - $length($insets[1] ?? 0.0, $width);
        $y1 = $top + $length($insets[0] ?? 0.0, $height);
        $y2 = $top + $height - $length($insets[2] ?? 0.0, $height);
        if ($x2 - $x1 <= 0 || $y2 - $y1 <= 0) {
            return static fn (float $from, float $to): ?array => null;
        }
        $corners = [];
        for ($i = 0; $i < 4; ++$i) {
            $corners[] = [max(0.0, $length($this->radii[2 * $i] ?? 0.0, $width)), max(0.0, $length($this->radii[2 * $i + 1] ?? 0.0, $height))];
        }
        // Like border-radius: radii too large for a side are all made smaller alike
        $scale = 1.0;
        foreach ([[0, 1, 0, $x2 - $x1], [3, 2, 0, $x2 - $x1], [0, 3, 1, $y2 - $y1], [1, 2, 1, $y2 - $y1]] as [$a, $b, $axis, $side]) {
            $sum = $corners[$a][$axis] + $corners[$b][$axis];
            if ($sum > $side) {
                $scale = min($scale, $side / $sum);
            }
        }
        foreach ($corners as $i => [$rx, $ry]) {
            $corners[$i] = $rx * $scale > 0 && $ry * $scale > 0 ? [$rx * $scale + $grow, $ry * $scale + $grow] : [$grow, $grow];
        }

        return self::roundedRectangle($x1 - $grow, $y1 - $grow, $x2 + $grow, $y2 + $grow, $corners);
    }

    /**
     * @param list<array{float, float}> $corners The radii of the top left, top right, bottom right and bottom left corners
     *
     * @return \Closure(float, float): ?array{float, float}
     */
    private static function roundedRectangle(float $x1, float $y1, float $x2, float $y2, array $corners): \Closure
    {
        // How far in an edge is at a height, in a corner: where the band is closest to the straight part of the edge
        $inside = static function (float $from, float $to, array $top, array $bottom) use ($y1, $y2): float {
            if ($top[1] > 0 && $to < $y1 + $top[1]) {
                $d = ($y1 + $top[1] - $to) / $top[1];

                return $top[0] * (1 - sqrt(max(0.0, 1 - $d * $d)));
            }
            if ($bottom[1] > 0 && $from > $y2 - $bottom[1]) {
                $d = ($from - $y2 + $bottom[1]) / $bottom[1];

                return $bottom[0] * (1 - sqrt(max(0.0, 1 - $d * $d)));
            }

            return 0.0;
        };

        return static function (float $from, float $to) use ($x1, $y1, $x2, $y2, $corners, $inside): ?array {
            $from = max($from, $y1);
            $to = min($to, $y2);
            if ($from > $to) {
                return null;
            }

            return [$x1 + $inside($from, $to, $corners[0], $corners[3]), $x2 - $inside($from, $to, $corners[1], $corners[2])];
        };
    }
}
