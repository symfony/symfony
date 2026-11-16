<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

/**
 * How a color is blended with the color behind it, like CSS mix-blend-mode and background-blend-mode.
 *
 * The formulas are those of the Compositing and Blending specification, on opaque colors.
 *
 * @see https://drafts.fxtf.org/compositing/#blending
 *
 * @experimental
 */
enum BlendMode: string
{
    case Normal = 'normal';
    case Multiply = 'multiply';
    case Screen = 'screen';
    case Overlay = 'overlay';
    case Darken = 'darken';
    case Lighten = 'lighten';
    case ColorDodge = 'color-dodge';
    case ColorBurn = 'color-burn';
    case HardLight = 'hard-light';
    case SoftLight = 'soft-light';
    case Difference = 'difference';
    case Exclusion = 'exclusion';
    case Hue = 'hue';
    case Saturation = 'saturation';
    case Color = 'color';
    case Luminosity = 'luminosity';
    /** Only for mix-blend-mode */
    case PlusLighter = 'plus-lighter';
    /** Only for mix-blend-mode */
    case PlusDarker = 'plus-darker';

    /**
     * The mode of a <blend-mode> keyword, the plus ones only when they are allowed, like in mix-blend-mode.
     */
    public static function fromKeyword(string $keyword, bool $plus = false): ?self
    {
        $mode = self::tryFrom(strtolower(trim($keyword)));

        return !$plus && (self::PlusLighter === $mode || self::PlusDarker === $mode) ? null : $mode;
    }

    /**
     * The color drawn for a source color over a backdrop.
     *
     * @param array{int, int, int} $backdrop
     * @param array{int, int, int} $source
     *
     * @return array{int, int, int}
     */
    public function blend(array $backdrop, array $source): array
    {
        if (self::Normal === $this) {
            return $source;
        }
        $cb = [$backdrop[0] / 255, $backdrop[1] / 255, $backdrop[2] / 255];
        $cs = [$source[0] / 255, $source[1] / 255, $source[2] / 255];
        $result = match ($this) {
            self::Hue => self::setLum(self::setSat($cs, self::sat($cb)), self::lum($cb)),
            self::Saturation => self::setLum(self::setSat($cb, self::sat($cs)), self::lum($cb)),
            self::Color => self::setLum($cs, self::lum($cb)),
            self::Luminosity => self::setLum($cb, self::lum($cs)),
            default => [$this->channel($cb[0], $cs[0]), $this->channel($cb[1], $cs[1]), $this->channel($cb[2], $cs[2])],
        };

        return [(int) round(max(0.0, min(1.0, $result[0])) * 255), (int) round(max(0.0, min(1.0, $result[1])) * 255), (int) round(max(0.0, min(1.0, $result[2])) * 255)];
    }

    /**
     * A separable mode, channel by channel.
     */
    private function channel(float $cb, float $cs): float
    {
        return match ($this) {
            self::Multiply => $cb * $cs,
            self::Screen => $cb + $cs - $cb * $cs,
            // Hard light with the layers swapped
            self::Overlay => self::HardLight->channel($cs, $cb),
            self::Darken => min($cb, $cs),
            self::Lighten => max($cb, $cs),
            self::ColorDodge => 0.0 === $cb ? 0.0 : (1.0 === $cs ? 1.0 : min(1.0, $cb / (1 - $cs))),
            self::ColorBurn => 1.0 === $cb ? 1.0 : (0.0 === $cs ? 0.0 : 1 - min(1.0, (1 - $cb) / $cs)),
            self::HardLight => $cs <= 0.5 ? self::Multiply->channel($cb, 2 * $cs) : self::Screen->channel($cb, 2 * $cs - 1),
            self::SoftLight => $cs <= 0.5 ? $cb - (1 - 2 * $cs) * $cb * (1 - $cb) : $cb + (2 * $cs - 1) * (($cb <= 0.25 ? ((16 * $cb - 12) * $cb + 4) * $cb : sqrt($cb)) - $cb),
            self::Difference => abs($cb - $cs),
            self::Exclusion => $cb + $cs - 2 * $cb * $cs,
            self::PlusLighter => min(1.0, $cb + $cs),
            self::PlusDarker => max(0.0, $cb + $cs - 1),
            default => $cs,
        };
    }

    /**
     * @param array{float, float, float} $c
     */
    private static function lum(array $c): float
    {
        return 0.3 * $c[0] + 0.59 * $c[1] + 0.11 * $c[2];
    }

    /**
     * @param array{float, float, float} $c
     *
     * @return array{float, float, float}
     */
    private static function setLum(array $c, float $l): array
    {
        $d = $l - self::lum($c);
        $c = [$c[0] + $d, $c[1] + $d, $c[2] + $d];
        // Clip the color into the gamut, keeping its luminosity
        $l = self::lum($c);
        $n = min($c);
        $x = max($c);
        if ($n < 0) {
            $c = array_map(static fn (float $v) => $l + ($v - $l) * $l / ($l - $n), $c);
        }
        if ($x > 1) {
            $c = array_map(static fn (float $v) => $l + ($v - $l) * (1 - $l) / ($x - $l), $c);
        }

        return $c;
    }

    /**
     * @param array{float, float, float} $c
     */
    private static function sat(array $c): float
    {
        return max($c) - min($c);
    }

    /**
     * @param array{float, float, float} $c
     *
     * @return array{float, float, float}
     */
    private static function setSat(array $c, float $s): array
    {
        $max = max($c);
        $min = min($c);
        if ($max <= $min) {
            return [0.0, 0.0, 0.0];
        }

        return array_map(static fn (float $v) => match (true) {
            $v === $max => $s,
            $v === $min => 0.0,
            default => ($v - $min) * $s / ($max - $min),
        }, $c);
    }
}
