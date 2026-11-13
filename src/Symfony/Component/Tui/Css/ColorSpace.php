<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Css;

/**
 * Converts colors between the CSS color spaces, through extended sRGB.
 *
 * The matrices and transfer functions are those of the CSS Color 4 sample
 * code. Channels are those of the CSS functions: hsl() and hwb() take
 * percentages from 0 to 100, lab() a lightness from 0 to 100, oklab() one
 * from 0 to 1, and the hues are in degrees.
 *
 * @experimental
 *
 * @internal
 */
final class ColorSpace
{
    /** The spaces color() takes */
    public const PREDEFINED = ['srgb', 'srgb-linear', 'display-p3', 'a98-rgb', 'prophoto-rgb', 'rec2020', 'xyz', 'xyz-d50', 'xyz-d65'];

    /** The spaces whose third channel is a hue */
    public const POLAR = ['hsl', 'hwb', 'lch', 'oklch'];

    /** The differences in OKLab below which two colors look the same */
    private const JND = 0.02;

    private const D50 = [0.3457 / 0.3585, 1.0, (1.0 - 0.3457 - 0.3585) / 0.3585];

    private const LINEAR_SRGB_TO_XYZ = [
        [0.41239079926595934, 0.357584339383878, 0.1804807884018343],
        [0.21263900587151027, 0.715168678767756, 0.07219231536073371],
        [0.01933081871559182, 0.11919477979462598, 0.9505321522496607],
    ];
    private const XYZ_TO_LINEAR_SRGB = [
        [3.2409699419045226, -1.537383177570094, -0.4986107602930034],
        [-0.9692436362808796, 1.8759675015077202, 0.04155505740717559],
        [0.05563007969699366, -0.20397695888897652, 1.0569715142428786],
    ];
    private const D50_TO_D65 = [
        [0.955473421488075, -0.02309845494876471, 0.06325924320057072],
        [-0.0283697093338637, 1.0099953980813041, 0.021041441191917323],
        [0.012314014864481998, -0.020507649298898964, 1.330365926242124],
    ];
    private const D65_TO_D50 = [
        [1.0479297925449969, 0.022946870601609652, -0.05019226628920524],
        [0.02962780877005599, 0.9904344267538799, -0.017073799063418826],
        [-0.009243040646204504, 0.015055191490298152, 0.7518742814281371],
    ];
    private const LINEAR_P3_TO_XYZ = [
        [0.4865709486482162, 0.26566769316909306, 0.1982172852343625],
        [0.2289745640697488, 0.6917385218365064, 0.079286914093745],
        [0.0, 0.04511338185890264, 1.043944368900976],
    ];
    private const XYZ_TO_LINEAR_P3 = [
        [2.493496911941425, -0.9313836179191239, -0.40271078445071684],
        [-0.8294889695615747, 1.7626640603183463, 0.023624685841943577],
        [0.03584583024378447, -0.07617238926804182, 0.9568845240076872],
    ];
    private const LINEAR_A98_TO_XYZ = [
        [0.5766690429101305, 0.1855582379065463, 0.1882286462349947],
        [0.29734497525053605, 0.6273635662554661, 0.07529145849399788],
        [0.02703136138641234, 0.07068885253582723, 0.9913375368376388],
    ];
    private const XYZ_TO_LINEAR_A98 = [
        [2.0415879038107465, -0.5650069742788596, -0.34473135077832956],
        [-0.9692436362808795, 1.8759675015077202, 0.04155505740717557],
        [0.013444280632031142, -0.11836239223101838, 1.0151749943912054],
    ];
    private const LINEAR_PROPHOTO_TO_XYZ_D50 = [
        [0.7977666449006423, 0.13518129740053308, 0.0313477341283922],
        [0.2880748288194013, 0.711835234241873, 0.00008993693872564],
        [0.0, 0.0, 0.8251046025104602],
    ];
    private const XYZ_D50_TO_LINEAR_PROPHOTO = [
        [1.3457868816471583, -0.25557208737979464, -0.05110186497554526],
        [-0.5446307051249019, 1.5082477428451468, 0.02052744743642139],
        [0.0, 0.0, 1.2119675456389452],
    ];
    private const LINEAR_REC2020_TO_XYZ = [
        [0.6369580483012914, 0.14461690358620832, 0.1688809751641721],
        [0.2627002120112671, 0.6779980715188708, 0.05930171646986196],
        [0.0, 0.028072693049087428, 1.060985057710791],
    ];
    private const XYZ_TO_LINEAR_REC2020 = [
        [1.716651187971268, -0.355670783776392, -0.25336628137366],
        [-0.666684351832489, 1.616481236634939, 0.0157685458139111],
        [0.017639857445311, -0.042770613257809, 0.942103121235474],
    ];

    /**
     * @param list<float> $channels
     *
     * @return list<float> Extended sRGB, out of [0, 1] when the color is out of its gamut
     */
    public static function toSrgb(string $space, array $channels): array
    {
        return match ($space) {
            'srgb' => $channels,
            'hsl' => self::hslToSrgb(...$channels),
            'hwb' => self::hwbToSrgb(...$channels),
            default => self::encodeSrgb(self::toLinearSrgb($space, $channels)),
        };
    }

    /**
     * @param list<float> $srgb Extended sRGB
     *
     * @return list<float>
     */
    public static function fromSrgb(string $space, array $srgb): array
    {
        return match ($space) {
            'srgb' => $srgb,
            'hsl' => self::srgbToHsl($srgb),
            'hwb' => self::srgbToHwb($srgb),
            default => self::fromLinearSrgb($space, self::decodeSrgb($srgb)),
        };
    }

    /**
     * Interpolates two colors in a space, like color-mix(): premultiplied by their alpha, an achromatic color taking the hue of the other.
     *
     * @param array{list<float>, float} $from The extended sRGB channels and the alpha
     * @param array{list<float>, float} $to
     *
     * @return array{list<float>, float}
     */
    public static function interpolate(string $space, string $hueMethod, array $from, array $to, float $t): array
    {
        $a = self::fromSrgb($space, $from[0]);
        $b = self::fromSrgb($space, $to[0]);
        $hue = match ($space) {
            'hsl', 'hwb' => 0,
            'lch', 'oklch' => 2,
            default => null,
        };

        if (null !== $hue) {
            $aPowerless = self::isPowerless($space, $a);
            $bPowerless = self::isPowerless($space, $b);
            if ($aPowerless && !$bPowerless) {
                $a[$hue] = $b[$hue];
            } elseif ($bPowerless && !$aPowerless) {
                $b[$hue] = $a[$hue];
            }
            $a[$hue] = fmod(fmod($a[$hue], 360) + 360, 360);
            $b[$hue] = fmod(fmod($b[$hue], 360) + 360, 360);
            $difference = $b[$hue] - $a[$hue];
            match ($hueMethod) {
                'longer' => $difference > 0 && $difference < 180 ? $a[$hue] += 360 : ($difference > -180 && $difference <= 0 ? $b[$hue] += 360 : null),
                'increasing' => $difference < 0 ? $b[$hue] += 360 : null,
                'decreasing' => $difference > 0 ? $a[$hue] += 360 : null,
                default => $difference > 180 ? $a[$hue] += 360 : ($difference < -180 ? $b[$hue] += 360 : null),
            };
        }

        $alpha = $from[1] * (1 - $t) + $to[1] * $t;
        $mixed = [];
        for ($i = 0; $i < 3; ++$i) {
            if ($i === $hue) {
                $mixed[] = fmod($a[$i] * (1 - $t) + $b[$i] * $t, 360);
                continue;
            }
            $premultiplied = $a[$i] * $from[1] * (1 - $t) + $b[$i] * $to[1] * $t;
            $mixed[] = $alpha > 0.0 ? $premultiplied / $alpha : 0.0;
        }

        return [self::toSrgb($space, $mixed), $alpha];
    }

    /**
     * Brings a color into the sRGB gamut, lowering its OKLCH chroma until clipping it would not be seen (CSS Color 4 gamut mapping).
     *
     * @param list<float> $srgb Extended sRGB
     *
     * @return list<float> sRGB in [0, 1]
     */
    public static function mapToSrgbGamut(array $srgb): array
    {
        if (self::inGamut($srgb)) {
            return self::clip($srgb);
        }

        [$lightness, $chroma, $hue] = self::fromSrgb('oklch', $srgb);
        if ($lightness >= 1.0) {
            return [1.0, 1.0, 1.0];
        }
        if ($lightness <= 0.0) {
            return [0.0, 0.0, 0.0];
        }

        $clipped = self::clip($srgb);
        if (self::deltaEok($clipped, $srgb) < self::JND) {
            return $clipped;
        }

        $min = 0.0;
        $max = $chroma;
        $minInGamut = true;
        while ($max - $min > 0.0001) {
            $current = self::toSrgb('oklch', [$lightness, ($min + $max) / 2, $hue]);
            if ($minInGamut && self::inGamut($current)) {
                $min = ($min + $max) / 2;
                continue;
            }
            $clipped = self::clip($current);
            $delta = self::deltaEok($clipped, $current);
            if ($delta >= self::JND) {
                $max = ($min + $max) / 2;
                continue;
            }
            if (self::JND - $delta < 0.0001) {
                return $clipped;
            }
            $minInGamut = false;
            $min = ($min + $max) / 2;
        }

        return $clipped;
    }

    /**
     * Whether the hue of a color means nothing, like that of a gray.
     *
     * @param list<float> $channels
     */
    private static function isPowerless(string $space, array $channels): bool
    {
        return match ($space) {
            'hsl' => $channels[1] < 0.01 || $channels[2] < 0.01 || $channels[2] > 99.99,
            'hwb' => $channels[1] + $channels[2] > 99.99,
            'lch' => $channels[1] < 0.01,
            default => $channels[1] < 0.0001,
        };
    }

    /**
     * @param list<float> $srgb
     */
    private static function inGamut(array $srgb): bool
    {
        return array_all($srgb, static fn (float $c) => $c >= -0.000001 && $c <= 1.000001);
    }

    /**
     * @param list<float> $srgb
     *
     * @return list<float>
     */
    private static function clip(array $srgb): array
    {
        return array_map(static fn (float $c) => max(0.0, min(1.0, $c)), $srgb);
    }

    /**
     * @param list<float> $a sRGB
     * @param list<float> $b sRGB
     */
    private static function deltaEok(array $a, array $b): float
    {
        $a = self::fromSrgb('oklab', $a);
        $b = self::fromSrgb('oklab', $b);

        return sqrt(($a[0] - $b[0]) ** 2 + ($a[1] - $b[1]) ** 2 + ($a[2] - $b[2]) ** 2);
    }

    /**
     * @param list<float> $channels
     *
     * @return list<float>
     */
    private static function toLinearSrgb(string $space, array $channels): array
    {
        return match ($space) {
            'srgb-linear' => $channels,
            'display-p3' => self::xyzToLinearSrgb(self::multiply(self::LINEAR_P3_TO_XYZ, self::decodeSrgb($channels))),
            'a98-rgb' => self::xyzToLinearSrgb(self::multiply(self::LINEAR_A98_TO_XYZ, array_map(static fn (float $c) => self::signedPower($c, 563 / 256), $channels))),
            'prophoto-rgb' => self::xyzToLinearSrgb(self::multiply(self::D50_TO_D65, self::multiply(self::LINEAR_PROPHOTO_TO_XYZ_D50, array_map(self::decodeProphoto(...), $channels)))),
            'rec2020' => self::xyzToLinearSrgb(self::multiply(self::LINEAR_REC2020_TO_XYZ, array_map(self::decodeRec2020(...), $channels))),
            'xyz', 'xyz-d65' => self::xyzToLinearSrgb($channels),
            'xyz-d50' => self::xyzToLinearSrgb(self::multiply(self::D50_TO_D65, $channels)),
            'lab' => self::xyzToLinearSrgb(self::multiply(self::D50_TO_D65, self::labToXyzD50($channels))),
            'lch' => self::toLinearSrgb('lab', self::polarToRectangular($channels)),
            'oklab' => self::oklabToLinearSrgb($channels),
            'oklch' => self::oklabToLinearSrgb(self::polarToRectangular($channels)),
            default => throw new \UnhandledMatchError(),
        };
    }

    /**
     * @param list<float> $linear
     *
     * @return list<float>
     */
    private static function fromLinearSrgb(string $space, array $linear): array
    {
        $xyz = self::multiply(self::LINEAR_SRGB_TO_XYZ, $linear);

        return match ($space) {
            'srgb-linear' => $linear,
            'display-p3' => self::encodeSrgb(self::multiply(self::XYZ_TO_LINEAR_P3, $xyz)),
            'a98-rgb' => array_map(static fn (float $c) => self::signedPower($c, 256 / 563), self::multiply(self::XYZ_TO_LINEAR_A98, $xyz)),
            'prophoto-rgb' => array_map(self::encodeProphoto(...), self::multiply(self::XYZ_D50_TO_LINEAR_PROPHOTO, self::multiply(self::D65_TO_D50, $xyz))),
            'rec2020' => array_map(self::encodeRec2020(...), self::multiply(self::XYZ_TO_LINEAR_REC2020, $xyz)),
            'xyz', 'xyz-d65' => $xyz,
            'xyz-d50' => self::multiply(self::D65_TO_D50, $xyz),
            'lab' => self::xyzD50ToLab(self::multiply(self::D65_TO_D50, $xyz)),
            'lch' => self::rectangularToPolar(self::xyzD50ToLab(self::multiply(self::D65_TO_D50, $xyz))),
            'oklab' => self::linearSrgbToOklab($linear),
            'oklch' => self::rectangularToPolar(self::linearSrgbToOklab($linear)),
            default => throw new \UnhandledMatchError(),
        };
    }

    /**
     * @param list<list<float>> $matrix
     * @param list<float>       $vector
     *
     * @return list<float>
     */
    private static function multiply(array $matrix, array $vector): array
    {
        return array_map(static fn (array $row) => $row[0] * $vector[0] + $row[1] * $vector[1] + $row[2] * $vector[2], $matrix);
    }

    /**
     * @param list<float> $xyz
     *
     * @return list<float>
     */
    private static function xyzToLinearSrgb(array $xyz): array
    {
        return self::multiply(self::XYZ_TO_LINEAR_SRGB, $xyz);
    }

    private static function signedPower(float $c, float $exponent): float
    {
        return ($c < 0 ? -1 : 1) * abs($c) ** $exponent;
    }

    /**
     * @param list<float> $srgb
     *
     * @return list<float>
     */
    private static function decodeSrgb(array $srgb): array
    {
        return array_map(static fn (float $c) => abs($c) <= 0.04045 ? $c / 12.92 : self::signedPower((abs($c) + 0.055) / 1.055, 2.4) * ($c < 0 ? -1 : 1), $srgb);
    }

    /**
     * @param list<float> $linear
     *
     * @return list<float>
     */
    private static function encodeSrgb(array $linear): array
    {
        return array_map(static fn (float $c) => abs($c) > 0.0031308 ? ($c < 0 ? -1 : 1) * (1.055 * abs($c) ** (1 / 2.4) - 0.055) : 12.92 * $c, $linear);
    }

    private static function decodeProphoto(float $c): float
    {
        return abs($c) <= 16 / 512 ? $c / 16 : self::signedPower($c, 1.8);
    }

    private static function encodeProphoto(float $c): float
    {
        return abs($c) >= 1 / 512 ? self::signedPower($c, 1 / 1.8) : 16 * $c;
    }

    private static function decodeRec2020(float $c): float
    {
        $alpha = 1.09929682680944;
        $beta = 0.018053968510807;

        return abs($c) < $beta * 4.5 ? $c / 4.5 : ($c < 0 ? -1 : 1) * ((abs($c) + $alpha - 1) / $alpha) ** (1 / 0.45);
    }

    private static function encodeRec2020(float $c): float
    {
        $alpha = 1.09929682680944;
        $beta = 0.018053968510807;

        return abs($c) > $beta ? ($c < 0 ? -1 : 1) * ($alpha * abs($c) ** 0.45 - ($alpha - 1)) : 4.5 * $c;
    }

    /**
     * @param list<float> $lab
     *
     * @return list<float>
     */
    private static function labToXyzD50(array $lab): array
    {
        $kappa = 24389 / 27;
        $epsilon = 216 / 24389;
        $f1 = ($lab[0] + 16) / 116;
        $f0 = $lab[1] / 500 + $f1;
        $f2 = $f1 - $lab[2] / 200;

        return [
            (($f0 ** 3) > $epsilon ? $f0 ** 3 : (116 * $f0 - 16) / $kappa) * self::D50[0],
            ($lab[0] > $kappa * $epsilon ? $f1 ** 3 : $lab[0] / $kappa) * self::D50[1],
            (($f2 ** 3) > $epsilon ? $f2 ** 3 : (116 * $f2 - 16) / $kappa) * self::D50[2],
        ];
    }

    /**
     * @param list<float> $xyz
     *
     * @return list<float>
     */
    private static function xyzD50ToLab(array $xyz): array
    {
        $kappa = 24389 / 27;
        $epsilon = 216 / 24389;
        $f = array_map(static fn (float $value, float $white) => ($value / $white) > $epsilon ? self::cubeRoot($value / $white) : ($kappa * $value / $white + 16) / 116, $xyz, self::D50);

        return [116 * $f[1] - 16, 500 * ($f[0] - $f[1]), 200 * ($f[1] - $f[2])];
    }

    /**
     * @param list<float> $oklab
     *
     * @return list<float>
     */
    private static function oklabToLinearSrgb(array $oklab): array
    {
        [$lightness, $a, $b] = $oklab;
        $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        return [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ];
    }

    /**
     * @param list<float> $linear
     *
     * @return list<float>
     */
    private static function linearSrgbToOklab(array $linear): array
    {
        [$r, $g, $b] = $linear;
        $l = self::cubeRoot(0.4122214708 * $r + 0.5363618370 * $g + 0.0514459929 * $b);
        $m = self::cubeRoot(0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b);
        $s = self::cubeRoot(0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b);

        return [
            0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s,
            1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s,
            0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s,
        ];
    }

    private static function cubeRoot(float $value): float
    {
        return self::signedPower($value, 1 / 3);
    }

    /**
     * @param list<float> $polar Lightness, chroma, hue in degrees
     *
     * @return list<float>
     */
    private static function polarToRectangular(array $polar): array
    {
        $chroma = max(0.0, $polar[1]);

        return [$polar[0], $chroma * cos(deg2rad($polar[2])), $chroma * sin(deg2rad($polar[2]))];
    }

    /**
     * @param list<float> $rectangular
     *
     * @return list<float> Lightness, chroma, hue in degrees from 0 to 360
     */
    private static function rectangularToPolar(array $rectangular): array
    {
        $hue = rad2deg(atan2($rectangular[2], $rectangular[1]));

        return [$rectangular[0], sqrt($rectangular[1] ** 2 + $rectangular[2] ** 2), $hue < 0 ? $hue + 360 : $hue];
    }

    /**
     * @return list<float>
     */
    private static function hslToSrgb(float $hue, float $saturation, float $lightness): array
    {
        $hue = fmod(fmod($hue, 360) + 360, 360) / 360;
        $saturation = max(0.0, min(1.0, $saturation / 100));
        $lightness = max(0.0, min(1.0, $lightness / 100));

        $channel = static function (float $offset) use ($hue, $saturation, $lightness): float {
            $k = fmod($offset + $hue * 12, 12);
            $a = $saturation * min($lightness, 1 - $lightness);

            return $lightness - $a * max(-1, min($k - 3, 9 - $k, 1));
        };

        return [$channel(0), $channel(8), $channel(4)];
    }

    /**
     * @return list<float>
     */
    private static function hwbToSrgb(float $hue, float $whiteness, float $blackness): array
    {
        $whiteness = max(0.0, $whiteness / 100);
        $blackness = max(0.0, $blackness / 100);
        if ($whiteness + $blackness >= 1) {
            $gray = $whiteness / ($whiteness + $blackness);

            return [$gray, $gray, $gray];
        }

        return array_map(static fn (float $c) => $c * (1 - $whiteness - $blackness) + $whiteness, self::hslToSrgb($hue, 100, 50));
    }

    /**
     * @param list<float> $srgb
     *
     * @return list<float>
     */
    private static function srgbToHsl(array $srgb): array
    {
        $max = max($srgb);
        $min = min($srgb);
        $lightness = ($max + $min) / 2;
        $delta = $max - $min;
        $hue = 0.0;
        $saturation = 0.0;
        if ($delta > 0.0) {
            $saturation = 0.0 === $lightness || 1.0 === $lightness ? 0.0 : ($max - $lightness) / min($lightness, 1 - $lightness);
            $hue = match ($max) {
                $srgb[0] => ($srgb[1] - $srgb[2]) / $delta + ($srgb[1] < $srgb[2] ? 6 : 0),
                $srgb[1] => ($srgb[2] - $srgb[0]) / $delta + 2,
                default => ($srgb[0] - $srgb[1]) / $delta + 4,
            } * 60;
        }

        return [$hue, $saturation * 100, $lightness * 100];
    }

    /**
     * @param list<float> $srgb
     *
     * @return list<float>
     */
    private static function srgbToHwb(array $srgb): array
    {
        return [self::srgbToHsl($srgb)[0], min($srgb) * 100, (1 - max($srgb)) * 100];
    }
}
