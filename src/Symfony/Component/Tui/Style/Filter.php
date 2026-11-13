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
 * Color filters, like CSS filter: grayscale(), sepia(), saturate(), hue-rotate(), brightness(), contrast(), invert() and opacity().
 *
 * They apply in order to every color of a widget and of what it contains,
 * in sRGB like the browsers, with the matrices of the Filter Effects spec.
 *
 * @experimental
 */
final class Filter
{
    public const FUNCTIONS = ['grayscale', 'sepia', 'saturate', 'hue-rotate', 'brightness', 'contrast', 'invert', 'opacity'];

    /**
     * @param list<array{string, float}> $functions Each function with its amount; hue-rotate takes degrees
     */
    public function __construct(
        public readonly array $functions,
    ) {
        foreach ($functions as [$function, $amount]) {
            if (!\in_array($function, self::FUNCTIONS, true)) {
                throw new InvalidArgumentException(\sprintf('Unknown filter function "%s".', $function));
            }
            if ($amount < 0 && 'hue-rotate' !== $function) {
                throw new InvalidArgumentException(\sprintf('The amount of "%s()" cannot be negative.', $function));
            }
        }
    }

    /**
     * The color drawn for a color, over what is behind it (for opacity()).
     *
     * @param array{int, int, int}|list<int> $rgb
     * @param array{int, int, int}|list<int> $behind
     *
     * @return array{int, int, int}
     */
    public function apply(array $rgb, array $behind = [0, 0, 0]): array
    {
        $color = array_map(static fn (int $c) => $c / 255, array_values($rgb));
        foreach ($this->functions as [$function, $amount]) {
            $color = match ($function) {
                'grayscale' => self::matrix($color, self::grayscale(min(1.0, $amount))),
                'sepia' => self::matrix($color, self::sepia(min(1.0, $amount))),
                'saturate' => self::matrix($color, self::saturate($amount)),
                'hue-rotate' => self::matrix($color, self::hueRotate($amount)),
                'brightness' => array_map(static fn (float $c) => $c * $amount, $color),
                'contrast' => array_map(static fn (float $c) => ($c - 0.5) * $amount + 0.5, $color),
                'invert' => array_map(static fn (float $c) => min(1.0, $amount) * (1 - $c) + (1 - min(1.0, $amount)) * $c, $color),
                'opacity' => array_map(static fn (float $c, int $b) => $c * min(1.0, $amount) + $b / 255 * (1 - min(1.0, $amount)), $color, array_values($behind)),
                default => throw new \UnhandledMatchError(),
            };
            // Each function works on colors in the gamut, like the filter primitives one after the other
            $color = array_map(static fn (float $c) => max(0.0, min(1.0, $c)), $color);
        }

        return [(int) round($color[0] * 255), (int) round($color[1] * 255), (int) round($color[2] * 255)];
    }

    /**
     * @param list<float>       $color
     * @param list<list<float>> $matrix
     *
     * @return list<float>
     */
    private static function matrix(array $color, array $matrix): array
    {
        return array_map(static fn (array $row) => $row[0] * $color[0] + $row[1] * $color[1] + $row[2] * $color[2], $matrix);
    }

    /**
     * @return list<list<float>>
     */
    private static function grayscale(float $amount): array
    {
        $s = 1 - $amount;

        return [
            [0.2126 + 0.7874 * $s, 0.7152 - 0.7152 * $s, 0.0722 - 0.0722 * $s],
            [0.2126 - 0.2126 * $s, 0.7152 + 0.2848 * $s, 0.0722 - 0.0722 * $s],
            [0.2126 - 0.2126 * $s, 0.7152 - 0.7152 * $s, 0.0722 + 0.9278 * $s],
        ];
    }

    /**
     * @return list<list<float>>
     */
    private static function sepia(float $amount): array
    {
        $s = 1 - $amount;

        return [
            [0.393 + 0.607 * $s, 0.769 - 0.769 * $s, 0.189 - 0.189 * $s],
            [0.349 - 0.349 * $s, 0.686 + 0.314 * $s, 0.168 - 0.168 * $s],
            [0.272 - 0.272 * $s, 0.534 - 0.534 * $s, 0.131 + 0.869 * $s],
        ];
    }

    /**
     * @return list<list<float>>
     */
    private static function saturate(float $s): array
    {
        return [
            [0.213 + 0.787 * $s, 0.715 - 0.715 * $s, 0.072 - 0.072 * $s],
            [0.213 - 0.213 * $s, 0.715 + 0.285 * $s, 0.072 - 0.072 * $s],
            [0.213 - 0.213 * $s, 0.715 - 0.715 * $s, 0.072 + 0.928 * $s],
        ];
    }

    /**
     * @return list<list<float>>
     */
    private static function hueRotate(float $degrees): array
    {
        $c = cos(deg2rad($degrees));
        $s = sin(deg2rad($degrees));

        return [
            [0.213 + $c * 0.787 - $s * 0.213, 0.715 - $c * 0.715 - $s * 0.715, 0.072 - $c * 0.072 + $s * 0.928],
            [0.213 - $c * 0.213 + $s * 0.143, 0.715 + $c * 0.285 + $s * 0.140, 0.072 - $c * 0.072 - $s * 0.283],
            [0.213 - $c * 0.213 - $s * 0.787, 0.715 - $c * 0.715 + $s * 0.715, 0.072 + $c * 0.928 + $s * 0.072],
        ];
    }
}
