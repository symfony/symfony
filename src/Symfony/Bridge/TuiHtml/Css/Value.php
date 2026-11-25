<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Css;

use Symfony\Bridge\TuiHtml\Style\Color;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Style\TailwindStylesheet;
use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;

/**
 * Helpers to read CSS component values.
 *
 * @experimental
 *
 * @internal
 */
final class Value
{
    /**
     * CSS Color 4 named colors, true colors.
     *
     * The ANSI names (black, red, green, yellow, blue, magenta, cyan, white,
     * gray and grey) are not listed: they keep their ANSI meaning, so that
     * they follow the palette of the terminal theme.
     */
    private const NAMED_COLORS = \Symfony\Component\HtmlRenderer\Css\ColorValue::NAMED_COLORS;
    /**
     * CSS system colors, in the light and the dark color schemes: Canvas and CanvasText are the terminal background and text of the scheme.
     */
    private const SYSTEM_COLORS = \Symfony\Component\HtmlRenderer\Css\ColorValue::SYSTEM_COLORS;

    /**
     * Splits a value on whitespace, keeping functions like rgb(1 2 3) and strings like "a b" whole.
     *
     * @return list<string>
     */
    public static function split(string $value): array
    {
        return \Symfony\Component\HtmlRenderer\Css\ValueTokenizer::split($value);
    }

    /**
     * The longest value var() substitutions may make, in bytes.
     */

    /**
     * The environment variables env() knows: a terminal has no notch, title bar overlay nor virtual keyboard.
     */
    private const ENVIRONMENT = ['safe-area-inset-top' => '0px', 'safe-area-inset-right' => '0px', 'safe-area-inset-bottom' => '0px', 'safe-area-inset-left' => '0px', 'safe-area-max-inset-top' => '0px', 'safe-area-max-inset-right' => '0px', 'safe-area-max-inset-bottom' => '0px', 'safe-area-max-inset-left' => '0px', 'keyboard-inset-top' => '0px', 'keyboard-inset-right' => '0px', 'keyboard-inset-bottom' => '0px', 'keyboard-inset-left' => '0px', 'keyboard-inset-width' => '0px', 'keyboard-inset-height' => '0px'];

    /**
     * Substitutes var(--name, fallback) and env(name, fallback) references.
     *
     * @param array<string, string> $properties The custom properties in scope
     *
     * @throws InvalidArgumentException when a reference cannot be resolved
     */
    public static function substitute(string $value, array $properties): string
    {
        return \Symfony\Component\HtmlRenderer\Css\CustomProperties::substitute($value, $properties, self::substituteEnvironment(...));
    }

    /**
     * Parses a CSS color.
     *
     * Supported: the 16 ANSI names (bright-red or bright_red), the other CSS
     * named colors, palette indexes (0-255), Tailwind shades (emerald-500),
     * #rgb and #rrggbb, rgb(), hsl(), hwb(), lab(), lch(), oklab(), oklch(),
     * color(), relative colors (rgb(from red r g b)), the system colors,
     * light-dark(), color-mix() and contrast-color().
     * "currentcolor" is resolved by {@see DeclarationMapper}.
     *
     * @return Color|false|null The color, null for "transparent", false when invalid
     */
    public static function color(string $value, MediaContext $media, ?Color $backdrop = null): Color|false|null
    {
        $color = self::opaqueColor($value, $media);
        $alpha = self::alpha($value);
        if (!$color instanceof Color || $alpha >= 1.0) {
            return $color;
        }
        if ($alpha <= 0.0) {
            return null;
        }
        // A terminal cell has one color: a translucent one is blended over what is behind, or the terminal background
        $backdrop ??= Color::hex(ColorScheme::Light === $media->colorScheme ? '#ffffff' : '#000000');

        return $backdrop->mix($color, (int) round($alpha * 100));
    }

    /**
     * Returns the alpha channel of a color, 1 when it is opaque or not a color with one.
     */
    public static function alpha(string $value, ?MediaContext $media = null): float
    {
        $value = strtolower(trim($value));
        if ('transparent' === $value) {
            return 0.0;
        }
        if (str_starts_with($value, 'color-mix(') || str_starts_with($value, 'light-dark(')) {
            return \is_array($srgba = self::srgba($value, $media ?? new MediaContext())) ? $srgba[1] : 1.0;
        }
        if (preg_match('/^#([0-9a-f]{4}|[0-9a-f]{8})$/', $value, $m)) {
            return hexdec(4 === \strlen($m[1]) ? str_repeat($m[1][3], 2) : substr($m[1], 6, 2)) / 255;
        }
        if (\is_array($function = self::colorFunction($value, $media ?? new MediaContext()))) {
            return $function[1];
        }

        return 1.0;
    }

    /**
     * @return Color|false|null The color without its alpha channel, null for none, false when it is not a color
     */
    public static function opaqueColor(string $value, MediaContext $media): Color|false|null
    {
        $value = strtolower(trim($value));
        if ('transparent' === $value || 'initial' === $value || 'none' === $value) {
            return null;
        }
        if (preg_match('/^light-dark\((.+)\)$/s', $value, $m)) {
            $choices = Selector::splitTopLevel($m[1]);
            if (2 !== \count($choices)) {
                return false;
            }

            return self::color($choices[ColorScheme::Light === $media->colorScheme ? 0 : 1], $media);
        }
        if (preg_match('/^color-mix\((.+)\)$/s', $value, $m)) {
            return \is_array($mixed = self::mix($m[1], $media)) ? ColorFunction::toColor($mixed[0]) : false;
        }
        if (preg_match('/^contrast-color\((.+)\)$/s', $value, $m)) {
            return \is_array($contrast = self::contrast($m[1], $media)) ? ColorFunction::toColor($contrast[0]) : false;
        }
        if (null !== $function = self::colorFunction($value, $media)) {
            return false === $function ? false : ColorFunction::toColor($function[0]);
        }
        if (null !== $system = self::SYSTEM_COLORS[$value][ColorScheme::Light === $media->colorScheme ? 0 : 1] ?? null) {
            return Color::hex($system);
        }
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $value)) {
            return Color::hex($value);
        }
        if (preg_match('/^#([0-9a-f]{3})[0-9a-f]$|^#([0-9a-f]{6})[0-9a-f]{2}$/', $value, $m)) {
            return Color::hex('#'.($m[1] ?: $m[2]));
        }
        if (preg_match('/^\d+$/', $value) && (int) $value <= 255) {
            return Color::palette((int) $value);
        }
        if (null !== $shade = TailwindStylesheet::getPaletteColor($value)) {
            return $shade;
        }
        try {
            return Color::named(str_replace('-', '_', $value));
        } catch (InvalidArgumentException) {
            return isset(self::NAMED_COLORS[$value]) ? Color::hex(self::NAMED_COLORS[$value]) : false;
        }
    }

    /**
     * Interpolates two colors in a color space, like the stops of linear-gradient(in oklch, ...): at 0 the first, at 1
     * the second.
     *
     * @param string $hueMethod shorter, longer, increasing or decreasing, for the polar spaces
     */
    public static function interpolate(Color $from, Color $to, float $t, string $space, string $hueMethod = 'shorter'): Color
    {
        $srgb = static fn (Color $color): array => [array_map(static fn (int $channel) => $channel / 255, array_values($color->toRgb())), 1.0];

        return ColorFunction::toColor(ColorSpace::interpolate($space, $hueMethod, $srgb($from), $srgb($to), $t)[0]);
    }

    /**
     * Reads a color interpolation method, like "in oklch longer hue": the space and the hue method; null when it is
     * not one.
     *
     * @return array{string, string}|null
     */
    public static function interpolationMethod(string $value): ?array
    {
        return \Symfony\Component\HtmlRenderer\Css\ColorValue::interpolationMethod($value);
    }

    /**
     * The offset of the first match of a pattern from an offset that is not in a quoted string, null when there is none.
     *
     * @internal
     */
    public static function findOutsideStrings(string $value, string $pattern, int $offset = 0): ?int
    {
        return \Symfony\Component\HtmlRenderer\Css\ValueTokenizer::findOutsideStrings($value, $pattern, $offset);
    }

    /**
     * @internal
     */
    public static function closingParenthesis(string $value, int $open): int
    {
        return \Symfony\Component\HtmlRenderer\Css\ValueTokenizer::closingParenthesis($value, $open);
    }

    /**
     * Substitutes env(name, fallback): the value of a known environment variable, else the fallback.
     *
     * @throws InvalidArgumentException when a variable is unknown and has no fallback
     */
    private static function substituteEnvironment(string $value): string
    {
        return \Symfony\Component\HtmlRenderer\Css\EnvironmentValues::substitute($value, static fn (string $name): ?string => self::ENVIRONMENT[$name] ?? null);
    }

    /**
     * @return array{list<float>, float}|false|null
     */
    private static function colorFunction(string $value, MediaContext $media): array|false|null
    {
        return ColorFunction::parse($value, static fn (string $origin) => self::srgba($origin, $media));
    }

    /**
     * Mixes two colors like color-mix(): in a color space (oklab by default), with a hue interpolation method for the polar ones.
     *
     * @return array{list<float>, float}|false The extended sRGB channels and the alpha
     */
    private static function mix(string $arguments, MediaContext $media): array|false
    {
        return \Symfony\Component\HtmlRenderer\Css\ColorValue::mix($arguments, static fn (string $color) => self::srgba($color, $media));
    }

    /**
     * Picks white or black, whichever contrasts the most with a color, like contrast-color().
     *
     * @return array{list<float>, float}|false The sRGB channels and the alpha
     */
    private static function contrast(string $argument, MediaContext $media): array|false
    {
        return \Symfony\Component\HtmlRenderer\Css\ColorValue::contrast($argument, static fn (string $color) => self::srgba($color, $media));
    }

    /**
     * @return array{list<float>, float}|false|null The extended sRGB channels and the alpha, null for none
     */
    private static function srgba(string $value, MediaContext $media): array|false|null
    {
        return \Symfony\Component\HtmlRenderer\Css\ColorValue::parse($value, ColorScheme::Light === $media->colorScheme ? 'light' : 'dark', static function (string $color) use ($media) {
            if (!($parsed = self::opaqueColor($color, $media)) instanceof Color) {
                return $parsed;
            }
            $rgb = $parsed->toRgb();

            return [[$rgb['r'] / 255, $rgb['g'] / 255, $rgb['b'] / 255], 1.0];
        });
    }

    /*
     * @return array{string, float|false|null} The color and its percentage, false when the percentage is out of range
     */
}
