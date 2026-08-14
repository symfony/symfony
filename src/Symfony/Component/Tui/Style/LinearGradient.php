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
 * Linear gradient background: colors distributed along a directional axis.
 *
 * Requires a truecolor terminal. Interpolating between two stops yields an
 * arbitrary color, so every cell is emitted as 48;2;R;G;B, including the ones
 * sitting exactly on a named or palette stop. The component detects no
 * capability, so on a 256-color terminal the area shows no background at all,
 * where Style::withBackground() would still render.
 *
 * @experimental
 */
final class LinearGradient implements GradientInterface
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
    private function __construct(array $stops, private readonly Angle $angle)
    {
        $this->stops = $stops;
    }

    /**
     * Create a LinearGradient from colors, or derive one from an existing gradient.
     *
     * @param self|array<Color|string|int|ColorStop> $colors    Gradient specification:
     *                                                          - LinearGradient instance: returned as-is, or re-angled when $direction is given
     *                                                          - array: at least 2 colors, color stops, or a mix of both
     * @param GradientDirection|Angle|null           $direction Gradient axis. Null keeps the direction of an existing
     *                                                          gradient, and defaults to left-to-right for an array.
     */
    public static function from(self|array $colors, GradientDirection|Angle|null $direction = null): self
    {
        if ($colors instanceof self) {
            if (null === $direction) {
                return $colors;
            }

            return new self($colors->stops, self::toAngle($direction));
        }

        // ColorStop::normalize() rejects a list of fewer than 2 colors
        return new self(ColorStop::normalize($colors), self::toAngle($direction ?? GradientDirection::LeftToRight));
    }

    private static function toAngle(GradientDirection|Angle $direction): Angle
    {
        return $direction instanceof Angle ? $direction : $direction->toAngle();
    }

    public static function leftToRight(Color|string|int|ColorStop ...$colors): self
    {
        return self::from($colors, Angle::degrees(0));
    }

    public static function rightToLeft(Color|string|int|ColorStop ...$colors): self
    {
        return self::from($colors, Angle::degrees(180));
    }

    public static function topToBottom(Color|string|int|ColorStop ...$colors): self
    {
        return self::from($colors, Angle::degrees(90));
    }

    public static function bottomToTop(Color|string|int|ColorStop ...$colors): self
    {
        return self::from($colors, Angle::degrees(270));
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
        $angleRad = $this->angle->toRadians();
        $dx = cos($angleRad);
        $dy = sin($angleRad);

        $xHalf = $width > 1 ? 0.5 : 0.0;
        $yHalf = $height > 1 ? 0.5 : 0.0;

        $pxMin = $dx >= 0 ? -$xHalf * $dx : $xHalf * $dx;
        $pxMax = $dx >= 0 ? $xHalf * $dx : -$xHalf * $dx;
        $pyMin = $dy >= 0 ? -$yHalf * $dy : $yHalf * $dy;
        $pyMax = $dy >= 0 ? $yHalf * $dy : -$yHalf * $dy;

        $minProj = $pxMin + $pyMin;
        $range = ($pxMax + $pyMax) - $minProj;

        $codes = [];
        for ($row = 0; $row < $height; ++$row) {
            $yNorm = $height > 1 ? $row / ($height - 1) - 0.5 : 0.0;
            $pyContrib = $yNorm * $dy;

            $rowCodes = [];
            for ($col = 0; $col < $width; ++$col) {
                $xNorm = $width > 1 ? $col / ($width - 1) - 0.5 : 0.0;
                $proj = $xNorm * $dx + $pyContrib;
                $offset = $range > 1e-10 ? ($proj - $minProj) / $range : 0.0;
                $offset = max(0.0, min(1.0, $offset));

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
