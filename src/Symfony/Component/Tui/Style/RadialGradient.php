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
 * Radial gradient background: colors radiate outward from a center point.
 *
 * The center is expressed in normalized coordinates: (0, 0) = top-left,
 * (0.5, 0.5) = center, (1, 1) = bottom-right. The farthest corner always
 * reaches offset=1 (last color stop). A center outside [0, 1] is allowed, as
 * in CSS.
 *
 * Requires a truecolor terminal. Interpolating between two stops yields an
 * arbitrary color, so every cell is emitted as 48;2;R;G;B, including the ones
 * sitting exactly on a named or palette stop. The component detects no
 * capability, so on a 256-color terminal the area shows no background at all,
 * where Style::withBackground() would still render.
 *
 * @experimental
 */
final class RadialGradient implements GradientInterface
{
    /** @var array<array{color: Color, offset: float}> Normalized stops, in declaration order */
    private readonly array $stops;

    /**
     * Codes of the last resolved size only. An app renders one size at a time, and
     * keeping every size ever asked for made these otherwise immutable value objects
     * grow without bound: 300 widths at height 24 cost around 91 MB.
     *
     * @var array<int, array<int, string>>|null
     */
    private ?array $cache = null;

    private string $cacheKey = '';

    /**
     * @param array<array{color: Color, offset: float}> $stops
     */
    private function __construct(
        array $stops,
        private readonly float $cx,
        private readonly float $cy,
        private readonly float $aspectRatio,
    ) {
        if (!is_finite($cx) || !is_finite($cy)) {
            throw new InvalidArgumentException(\sprintf('The center of a radial gradient must be finite, got "%s" and "%s".', var_export($cx, true), var_export($cy, true)));
        }
        if (!is_finite($aspectRatio) || $aspectRatio <= 0) {
            throw new InvalidArgumentException(\sprintf('The aspect ratio of a radial gradient must be a finite number greater than 0, got "%s".', var_export($aspectRatio, true)));
        }

        $this->stops = $stops;
    }

    /**
     * Create a RadialGradient from colors, or derive one from an existing gradient.
     *
     * Each geometry argument is optional: null keeps the value of an existing gradient, and falls back to the
     * default (center, 2.0) for an array. A non-null value always overrides the one of an existing gradient.
     *
     * @param self|array<Color|string|int|ColorStop> $colors      Gradient specification:
     *                                                            - RadialGradient instance: returned as-is, or re-centered when a geometry argument is given
     *                                                            - array: at least 2 colors, color stops, or a mix of both
     * @param float|null                             $cx          Horizontal center in normalized coordinates [0, 1] (0=left, 0.5=center, 1=right)
     * @param float|null                             $cy          Vertical center in normalized coordinates [0, 1] (0=top, 0.5=center, 1=bottom)
     * @param float|null                             $aspectRatio Cell height / cell width ratio. Standard terminals use ~2.0 (cells are
     *                                                            roughly twice as tall as wide). Set to 1.0 for square-pixel rendering.
     */
    public static function from(self|array $colors, ?float $cx = null, ?float $cy = null, ?float $aspectRatio = null): self
    {
        if ($colors instanceof self) {
            if (null === $cx && null === $cy && null === $aspectRatio) {
                return $colors;
            }

            return new self($colors->stops, $cx ?? $colors->cx, $cy ?? $colors->cy, $aspectRatio ?? $colors->aspectRatio);
        }

        // ColorStop::normalize() rejects a list of fewer than 2 colors
        return new self(ColorStop::normalize($colors), $cx ?? 0.5, $cy ?? 0.5, $aspectRatio ?? 2.0);
    }

    public static function centered(Color|string|int|ColorStop ...$colors): self
    {
        return self::from($colors);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function resolve(int $width, int $height): array
    {
        $key = $width.':'.$height;
        if ($key === $this->cacheKey && null !== $this->cache) {
            return $this->cache;
        }

        $this->cacheKey = $key;

        return $this->cache = $this->compute($width, $height);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function compute(int $width, int $height): array
    {
        // Convert normalized center to pixel coordinates
        $cxPx = $this->cx * ($width > 1 ? $width - 1 : 0);
        $cyPx = $this->cy * ($height > 1 ? $height - 1 : 0);

        // Aspect-ratio-corrected distance: dy is scaled so 1 row == $aspectRatio columns
        // in visual distance, matching the physical pixel dimensions of terminal cells.
        $ar = $this->aspectRatio;

        $maxDist = max(
            sqrt($cxPx ** 2 + ($cyPx * $ar) ** 2),
            sqrt(($width - 1 - $cxPx) ** 2 + ($cyPx * $ar) ** 2),
            sqrt($cxPx ** 2 + (($height - 1 - $cyPx) * $ar) ** 2),
            sqrt(($width - 1 - $cxPx) ** 2 + (($height - 1 - $cyPx) * $ar) ** 2),
        );

        $codes = [];
        for ($row = 0; $row < $height; ++$row) {
            $dy = ($row - $cyPx) * $ar;
            $rowCodes = [];
            for ($col = 0; $col < $width; ++$col) {
                $dx = $col - $cxPx;
                $dist = sqrt($dx ** 2 + $dy ** 2);
                $offset = $maxDist > 1e-10 ? min(1.0, $dist / $maxDist) : 0.0;
                $rowCodes[$col] = $this->interpolate($offset);
            }
            $codes[$row] = $rowCodes;
        }

        return $codes;
    }

    private function interpolate(float $offset): string
    {
        $stops = $this->stops;
        $n = \count($stops);

        if ($offset <= $stops[0]['offset']) {
            return self::backgroundCode($stops[0]['color'], $stops[0]['color'], 0.0);
        }
        if ($offset >= $stops[$n - 1]['offset']) {
            return self::backgroundCode($stops[$n - 1]['color'], $stops[$n - 1]['color'], 0.0);
        }

        for ($i = 0; $i < $n - 1; ++$i) {
            if ($offset >= $stops[$i]['offset'] && $offset <= $stops[$i + 1]['offset']) {
                $segmentRange = $stops[$i + 1]['offset'] - $stops[$i]['offset'];
                $localOffset = $segmentRange > 1e-10 ? ($offset - $stops[$i]['offset']) / $segmentRange : 0.0;

                return self::backgroundCode($stops[$i]['color'], $stops[$i + 1]['color'], $localOffset);
            }
        }

        return self::backgroundCode($stops[$n - 1]['color'], $stops[$n - 1]['color'], 0.0);
    }

    /**
     * Mix two stop colors channel by channel and return the resulting bg code.
     *
     * Color::mix() rounds the ratio to an integer percentage, which caps a
     * segment at 101 distinct colors: a two-stop gradient bands from about 100
     * columns on. Keeping the ratio in float removes that ceiling.
     *
     * Both endpoints go through the same path, so a named or palette color is
     * emitted as truecolor there too, instead of showing as a band of its own
     * SGR code next to the interpolated cells.
     */
    private static function backgroundCode(Color $from, Color $to, float $ratio): string
    {
        $a = $from->toRgb();
        $b = $to->toRgb();

        return Color::rgb(
            (int) round($a['r'] + ($b['r'] - $a['r']) * $ratio),
            (int) round($a['g'] + ($b['g'] - $a['g']) * $ratio),
            (int) round($a['b'] + ($b['b'] - $a['b']) * $ratio),
        )->toBackgroundCode();
    }
}
