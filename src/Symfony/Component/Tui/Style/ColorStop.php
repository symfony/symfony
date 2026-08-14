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

use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * A color stop: a color with an explicit position (0–100) along a gradient.
 *
 * When passed to a gradient, stops with no explicit position are automatically
 * distributed between their neighbours (CSS-compatible algorithm):
 *   - first stop without position → 0
 *   - last stop without position → 100
 *   - intermediate stops without position → evenly interpolated between neighbours
 *
 * Stops are never reordered: a position lower than the one before it is clamped up
 * to it, which makes the two colors meet at a hard stop, as CSS does.
 *
 * @experimental
 */
final class ColorStop
{
    public readonly Color $color;

    private function __construct(Color $color, public readonly int $position)
    {
        $this->color = $color;
    }

    public static function at(Color|string|int $color, int $position): self
    {
        if ($position < 0 || $position > 100) {
            throw new InvalidArgumentException(\sprintf('Color stop position must be between 0 and 100, got %d.', $position));
        }

        return new self(Color::from($color), $position);
    }

    /**
     * Normalizes a mixed array of plain colors and ColorStops into an array of
     * [color => Color, offset => float] entries where offset ∈ [0.0, 1.0].
     *
     * Plain colors (Color|string|int) without an explicit position receive one
     * automatically using the CSS interpolation algorithm.
     *
     * @param array<Color|string|int|ColorStop> $input
     *
     * @return array<array{color: Color, offset: float}>
     */
    public static function normalize(array $input): array
    {
        // Convert to [color, pos: float|null] pairs preserving order
        /** @var array<array{color: Color, pos: float|null}> $pairs */
        $pairs = [];
        foreach ($input as $item) {
            if ($item instanceof self) {
                $pairs[] = ['color' => $item->color, 'pos' => (float) $item->position];
            } else {
                $pairs[] = ['color' => Color::from($item), 'pos' => null];
            }
        }

        $n = \count($pairs);

        if ($n < 2) {
            throw new InvalidArgumentException('A gradient requires at least 2 colors.');
        }

        // First and last plain stops default to 0 and 100
        if (null === $pairs[0]['pos']) {
            $pairs[0]['pos'] = 0.0;
        }
        if (null === $pairs[$n - 1]['pos']) {
            $pairs[$n - 1]['pos'] = 100.0;
        }

        // Fill each run of un-positioned stops by linear interpolation between neighbours
        $i = 0;
        while ($i < $n) {
            if (null !== $pairs[$i]['pos']) {
                ++$i;
                continue;
            }
            $prev = $i - 1;
            $j = $i;
            while ($j < $n && null === $pairs[$j]['pos']) {
                ++$j;
            }
            $intervals = $j - $prev;
            for ($k = $i; $k < $j; ++$k) {
                $pairs[$k]['pos'] = $pairs[$prev]['pos']
                    + ($pairs[$j]['pos'] - $pairs[$prev]['pos']) * ($k - $prev) / $intervals;
            }
            $i = $j;
        }

        // Clamp each position up to the highest one seen so far. A stop that goes
        // backwards becomes a hard stop instead of being moved earlier in the
        // gradient, which is what CSS does.
        $stops = [];
        $highest = 0.0;
        foreach ($pairs as $pair) {
            $highest = max($highest, $pair['pos']);
            $stops[] = ['color' => $pair['color'], 'offset' => $highest / 100.0];
        }

        return $stops;
    }
}
