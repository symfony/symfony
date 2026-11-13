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

use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\ColorScheme;
use Symfony\Component\Tui\Style\MediaContext;
use Symfony\Component\Tui\Style\TailwindStylesheet;

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
    private const NAMED_COLORS = [
        'aliceblue' => '#f0f8ff',
        'antiquewhite' => '#faebd7',
        'aqua' => '#00ffff',
        'aquamarine' => '#7fffd4',
        'azure' => '#f0ffff',
        'beige' => '#f5f5dc',
        'bisque' => '#ffe4c4',
        'blanchedalmond' => '#ffebcd',
        'blueviolet' => '#8a2be2',
        'brown' => '#a52a2a',
        'burlywood' => '#deb887',
        'cadetblue' => '#5f9ea0',
        'chartreuse' => '#7fff00',
        'chocolate' => '#d2691e',
        'coral' => '#ff7f50',
        'cornflowerblue' => '#6495ed',
        'cornsilk' => '#fff8dc',
        'crimson' => '#dc143c',
        'darkblue' => '#00008b',
        'darkcyan' => '#008b8b',
        'darkgoldenrod' => '#b8860b',
        'darkgray' => '#a9a9a9',
        'darkgreen' => '#006400',
        'darkgrey' => '#a9a9a9',
        'darkkhaki' => '#bdb76b',
        'darkmagenta' => '#8b008b',
        'darkolivegreen' => '#556b2f',
        'darkorange' => '#ff8c00',
        'darkorchid' => '#9932cc',
        'darkred' => '#8b0000',
        'darksalmon' => '#e9967a',
        'darkseagreen' => '#8fbc8f',
        'darkslateblue' => '#483d8b',
        'darkslategray' => '#2f4f4f',
        'darkslategrey' => '#2f4f4f',
        'darkturquoise' => '#00ced1',
        'darkviolet' => '#9400d3',
        'deeppink' => '#ff1493',
        'deepskyblue' => '#00bfff',
        'dimgray' => '#696969',
        'dimgrey' => '#696969',
        'dodgerblue' => '#1e90ff',
        'firebrick' => '#b22222',
        'floralwhite' => '#fffaf0',
        'forestgreen' => '#228b22',
        'fuchsia' => '#ff00ff',
        'gainsboro' => '#dcdcdc',
        'ghostwhite' => '#f8f8ff',
        'gold' => '#ffd700',
        'goldenrod' => '#daa520',
        'greenyellow' => '#adff2f',
        'honeydew' => '#f0fff0',
        'hotpink' => '#ff69b4',
        'indianred' => '#cd5c5c',
        'indigo' => '#4b0082',
        'ivory' => '#fffff0',
        'khaki' => '#f0e68c',
        'lavender' => '#e6e6fa',
        'lavenderblush' => '#fff0f5',
        'lawngreen' => '#7cfc00',
        'lemonchiffon' => '#fffacd',
        'lightblue' => '#add8e6',
        'lightcoral' => '#f08080',
        'lightcyan' => '#e0ffff',
        'lightgoldenrodyellow' => '#fafad2',
        'lightgray' => '#d3d3d3',
        'lightgreen' => '#90ee90',
        'lightgrey' => '#d3d3d3',
        'lightpink' => '#ffb6c1',
        'lightsalmon' => '#ffa07a',
        'lightseagreen' => '#20b2aa',
        'lightskyblue' => '#87cefa',
        'lightslategray' => '#778899',
        'lightslategrey' => '#778899',
        'lightsteelblue' => '#b0c4de',
        'lightyellow' => '#ffffe0',
        'lime' => '#00ff00',
        'limegreen' => '#32cd32',
        'linen' => '#faf0e6',
        'maroon' => '#800000',
        'mediumaquamarine' => '#66cdaa',
        'mediumblue' => '#0000cd',
        'mediumorchid' => '#ba55d3',
        'mediumpurple' => '#9370db',
        'mediumseagreen' => '#3cb371',
        'mediumslateblue' => '#7b68ee',
        'mediumspringgreen' => '#00fa9a',
        'mediumturquoise' => '#48d1cc',
        'mediumvioletred' => '#c71585',
        'midnightblue' => '#191970',
        'mintcream' => '#f5fffa',
        'mistyrose' => '#ffe4e1',
        'moccasin' => '#ffe4b5',
        'navajowhite' => '#ffdead',
        'navy' => '#000080',
        'oldlace' => '#fdf5e6',
        'olive' => '#808000',
        'olivedrab' => '#6b8e23',
        'orange' => '#ffa500',
        'orangered' => '#ff4500',
        'orchid' => '#da70d6',
        'palegoldenrod' => '#eee8aa',
        'palegreen' => '#98fb98',
        'paleturquoise' => '#afeeee',
        'palevioletred' => '#db7093',
        'papayawhip' => '#ffefd5',
        'peachpuff' => '#ffdab9',
        'peru' => '#cd853f',
        'pink' => '#ffc0cb',
        'plum' => '#dda0dd',
        'powderblue' => '#b0e0e6',
        'purple' => '#800080',
        'rebeccapurple' => '#663399',
        'rosybrown' => '#bc8f8f',
        'royalblue' => '#4169e1',
        'saddlebrown' => '#8b4513',
        'salmon' => '#fa8072',
        'sandybrown' => '#f4a460',
        'seagreen' => '#2e8b57',
        'seashell' => '#fff5ee',
        'sienna' => '#a0522d',
        'silver' => '#c0c0c0',
        'skyblue' => '#87ceeb',
        'slateblue' => '#6a5acd',
        'slategray' => '#708090',
        'slategrey' => '#708090',
        'snow' => '#fffafa',
        'springgreen' => '#00ff7f',
        'steelblue' => '#4682b4',
        'tan' => '#d2b48c',
        'teal' => '#008080',
        'thistle' => '#d8bfd8',
        'tomato' => '#ff6347',
        'turquoise' => '#40e0d0',
        'violet' => '#ee82ee',
        'wheat' => '#f5deb3',
        'whitesmoke' => '#f5f5f5',
        'yellowgreen' => '#9acd32',
    ];

    /**
     * CSS system colors, in the light and the dark color schemes: Canvas and CanvasText are the terminal background and text of the scheme.
     */
    private const SYSTEM_COLORS = [
        'canvas' => ['#ffffff', '#000000'],
        'canvastext' => ['#000000', '#ffffff'],
        'linktext' => ['#0000ee', '#9e9eff'],
        // The WebKit names of the colors of links
        '-webkit-link' => ['#0000ee', '#9e9eff'],
        '-webkit-activelink' => ['#ff0000', '#ff9e9e'],
        'visitedtext' => ['#551a8b', '#d0adf0'],
        'activetext' => ['#ff0000', '#ff9e9e'],
        'buttonface' => ['#efefef', '#3b3b3b'],
        'buttontext' => ['#000000', '#ffffff'],
        'buttonborder' => ['#767676', '#858585'],
        'field' => ['#ffffff', '#3b3b3b'],
        'fieldtext' => ['#000000', '#ffffff'],
        'highlight' => ['#b5d5ff', '#264f78'],
        'highlighttext' => ['#000000', '#ffffff'],
        'selecteditem' => ['#0075ff', '#99c8ff'],
        'selecteditemtext' => ['#ffffff', '#000000'],
        'mark' => ['#ffff00', '#ffff00'],
        'marktext' => ['#000000', '#000000'],
        'graytext' => ['#6d6d6d', '#8d8d8d'],
        'accentcolor' => ['#0075ff', '#99c8ff'],
        'accentcolortext' => ['#ffffff', '#000000'],
    ];

    /**
     * Splits a value on whitespace, keeping functions like rgb(1 2 3) and strings like "a b" whole.
     *
     * @return list<string>
     */
    public static function split(string $value): array
    {
        // Most values hold no function nor string: whitespace alone splits them
        if (false === strpbrk($value, '("\'')) {
            return preg_split('/\s+/', $value, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        }
        $parts = [];
        $current = '';
        $depth = 0;
        $length = \strlen($value);

        for ($i = 0; $i < $length; ++$i) {
            $char = $value[$i];
            if ('"' === $char || "'" === $char) {
                // A string, its escaped quotes included
                $end = $i + 1;
                while ($end < $length && $value[$end] !== $char) {
                    $end += '\\' === $value[$end] ? 2 : 1;
                }
                $current .= substr($value, $i, $end - $i + 1);
                $i = $end;
                continue;
            }
            if ('(' === $char) {
                ++$depth;
            } elseif (')' === $char) {
                --$depth;
            } elseif (0 === $depth && ctype_space($char)) {
                if ('' !== $current) {
                    $parts[] = $current;
                    $current = '';
                }
                continue;
            }
            $current .= $char;
        }

        if ('' !== $current) {
            $parts[] = $current;
        }

        return $parts;
    }

    /**
     * The environment variables env() knows: a terminal has no notch, title bar overlay nor virtual keyboard.
     */
    private const ENVIRONMENT = [
        'safe-area-inset-top' => '0px', 'safe-area-inset-right' => '0px', 'safe-area-inset-bottom' => '0px', 'safe-area-inset-left' => '0px',
        'safe-area-max-inset-top' => '0px', 'safe-area-max-inset-right' => '0px', 'safe-area-max-inset-bottom' => '0px', 'safe-area-max-inset-left' => '0px',
        'keyboard-inset-top' => '0px', 'keyboard-inset-right' => '0px', 'keyboard-inset-bottom' => '0px', 'keyboard-inset-left' => '0px',
        'keyboard-inset-width' => '0px', 'keyboard-inset-height' => '0px',
    ];

    /**
     * Substitutes var(--name, fallback) and env(name, fallback) references.
     *
     * @param array<string, string> $properties The custom properties in scope
     *
     * @throws InvalidArgumentException when a reference cannot be resolved
     */
    public static function substitute(string $value, array $properties, int $depth = 0): string
    {
        if (str_contains($value, 'env(')) {
            $value = self::substituteEnvironment($value);
        }
        if (!str_contains($value, 'var(')) {
            return $value;
        }
        if ($depth > 32) {
            throw new InvalidArgumentException('Custom properties reference each other in a cycle.');
        }

        $offset = 0;
        while (false !== $start = strpos($value, 'var(', $offset)) {
            $end = self::closingParenthesis($value, $start + 3);
            // The name, up to the first comma, and the fallback, all after it as written, commas too, like CSS: empty
            // in the var(--tw-ring-inset,) of the rings of Tailwind, a comma in var(--a,,)
            $arguments = substr($value, $start + 4, $end - $start - 4);
            $comma = strpos($arguments, ',');
            $name = trim(false === $comma ? $arguments : substr($arguments, 0, $comma));
            $replacement = $properties[$name] ?? null;
            // Set to initial, a custom property is the guaranteed-invalid value: the fallback, like CSS, like the
            // --bs-table-color-state: initial of the tables of Bootstrap
            if (null !== $replacement && 'initial' === strtolower(trim($replacement))) {
                $replacement = null;
            }
            $replacement ??= false === $comma ? null : trim(substr($arguments, $comma + 1));
            if (null === $replacement) {
                throw new InvalidArgumentException(\sprintf('The custom property "%s" is not defined.', $name));
            }
            $replacement = self::substitute($replacement, $properties, $depth + 1);
            // Tokens, not text, like CSS: a value set against a word or a number stays apart from it, like the
            // var(--bs-border-width)var(--bs-border-style) of a minified Bootstrap, 1px solid and not 1pxsolid
            $before = substr($value, 0, $start);
            $after = substr($value, $end + 1);
            if ('' !== ($trimmed = trim($replacement))) {
                if (preg_match('/[\w%)]$/', $before) && preg_match('/^[\w.#+-]/', $trimmed)) {
                    $replacement = ' '.$replacement;
                }
                if (preg_match('/^[\w.#(]/', $after) && preg_match('/[\w%)]$/', $trimmed)) {
                    $replacement .= ' ';
                }
            }
            $value = $before.$replacement.$after;
            $offset = $start + \strlen($replacement);
        }

        return $value;
    }

    /**
     * Substitutes env(name, fallback): the value of a known environment variable, else the fallback.
     *
     * @throws InvalidArgumentException when a variable is unknown and has no fallback
     */
    private static function substituteEnvironment(string $value): string
    {
        $offset = 0;
        while (null !== $start = self::findOutsideStrings($value, '/(?<![\w-])env\(/i', $offset)) {
            $end = self::closingParenthesis($value, $start + 3);
            $arguments = Selector::splitTopLevel(substr($value, $start + 4, $end - $start - 4));
            // The indices after the name, like env(viewport-segment-width 0 0), name segments: there are none here
            $name = strtolower(trim($arguments[0] ?? ''));
            $replacement = self::ENVIRONMENT[$name] ?? (isset($arguments[1]) ? implode(', ', \array_slice($arguments, 1)) : null);
            if (null === $replacement) {
                throw new InvalidArgumentException(\sprintf('The environment variable "%s" is not defined.', $name));
            }
            $value = substr($value, 0, $start).$replacement.substr($value, $end + 1);
            $offset = $start + \strlen($replacement);
        }

        return $value;
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
     * @return array{list<float>, float}|false|null
     */
    private static function colorFunction(string $value, MediaContext $media): array|false|null
    {
        return ColorFunction::parse($value, static fn (string $origin) => self::srgba($origin, $media));
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
        if (!preg_match('/^in\s+([a-z0-9-]+)(?:\s+(shorter|longer|increasing|decreasing)\s+hue)?$/', strtolower(trim($value)), $m)) {
            return null;
        }
        if (!\in_array($m[1], [...ColorSpace::PREDEFINED, ...ColorSpace::POLAR, 'lab', 'oklab'], true) || ('' !== ($m[2] ?? '') && !\in_array($m[1], ColorSpace::POLAR, true))) {
            return null;
        }

        return [$m[1], $m[2] ?? '' ?: 'shorter'];
    }

    /**
     * Mixes two colors like color-mix(): in a color space (oklab by default), with a hue interpolation method for the polar ones.
     *
     * @return array{list<float>, float}|false The extended sRGB channels and the alpha
     */
    private static function mix(string $arguments, MediaContext $media): array|false
    {
        $parts = Selector::splitTopLevel($arguments);
        $space = 'oklab';
        $hueMethod = 'shorter';
        if ($parts && str_starts_with($parts[0], 'in ')) {
            if (null === $method = self::interpolationMethod($parts[0])) {
                return false;
            }
            [$space, $hueMethod] = $method;
            array_shift($parts);
        }
        if (2 !== \count($parts)) {
            return false;
        }

        [$first, $firstPercentage] = self::colorStop($parts[0]);
        [$second, $secondPercentage] = self::colorStop($parts[1]);
        if (false === $firstPercentage || false === $secondPercentage) {
            return false;
        }
        $firstPercentage ??= null !== $secondPercentage ? 100 - $secondPercentage : 50;
        $secondPercentage ??= 100 - $firstPercentage;
        $sum = $firstPercentage + $secondPercentage;
        if ($sum <= 0) {
            return false;
        }
        if (!\is_array($first = self::srgba($first, $media)) || !\is_array($second = self::srgba($second, $media))) {
            return false;
        }

        [$srgb, $alpha] = ColorSpace::interpolate($space, $hueMethod, $first, $second, $secondPercentage / $sum);

        // Percentages summing to less than 100% make the mix translucent
        return [$srgb, $alpha * min(1.0, $sum / 100)];
    }

    /**
     * Picks white or black, whichever contrasts the most with a color, like contrast-color().
     *
     * @return array{list<float>, float}|false The sRGB channels and the alpha
     */
    private static function contrast(string $argument, MediaContext $media): array|false
    {
        if (!\is_array($srgba = self::srgba($argument, $media))) {
            return false;
        }

        // The WCAG relative luminance: white contrasts more below about 0.179, where both contrast ratios are equal
        $linear = static fn (float $c): float => ($c = max(0.0, min(1.0, $c))) <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        [$r, $g, $b] = $srgba[0];
        $luminance = 0.2126 * $linear($r) + 0.7152 * $linear($g) + 0.0722 * $linear($b);
        $white = 1.05 / ($luminance + 0.05);
        $black = ($luminance + 0.05) / 0.05;

        return [$white >= $black ? [1.0, 1.0, 1.0] : [0.0, 0.0, 0.0], 1.0];
    }

    /**
     * @return array{list<float>, float}|false|null The extended sRGB channels and the alpha, null for none
     */
    private static function srgba(string $value, MediaContext $media): array|false|null
    {
        $value = strtolower(trim($value));
        if ('transparent' === $value) {
            return [[0.0, 0.0, 0.0], 0.0];
        }
        if (preg_match('/^color-mix\((.+)\)$/s', $value, $m)) {
            return self::mix($m[1], $media);
        }
        if (preg_match('/^contrast-color\((.+)\)$/s', $value, $m)) {
            return self::contrast($m[1], $media);
        }
        if (preg_match('/^light-dark\((.+)\)$/s', $value, $m)) {
            $choices = Selector::splitTopLevel($m[1]);

            return 2 === \count($choices) ? self::srgba($choices[ColorScheme::Light === $media->colorScheme ? 0 : 1], $media) : false;
        }
        if (null !== $function = self::colorFunction($value, $media)) {
            return $function;
        }
        if (!($color = self::opaqueColor($value, $media)) instanceof Color) {
            return $color;
        }
        $rgb = $color->toRgb();

        return [[$rgb['r'] / 255, $rgb['g'] / 255, $rgb['b'] / 255], self::alpha($value)];
    }

    /**
     * @return array{string, float|false|null} The color and its percentage, false when the percentage is out of range
     */
    private static function colorStop(string $stop): array
    {
        $stop = trim($stop);
        // The percentage goes after the color, or before it
        if (preg_match('/^(.+?)\s+(-?\d*\.?\d+)%$/', $stop, $m)) {
            [, $color, $percentage] = $m;
        } elseif (preg_match('/^(-?\d*\.?\d+)%\s+(.+)$/', $stop, $m)) {
            [, $percentage, $color] = $m;
        } else {
            return [$stop, null];
        }

        return [$color, (float) $percentage < 0 || (float) $percentage > 100 ? false : (float) $percentage];
    }

    /**
     * The offset of the first match of a pattern from an offset that is not in a quoted string, null when there is none.
     *
     * @internal
     */
    public static function findOutsideStrings(string $value, string $pattern, int $offset = 0): ?int
    {
        while (preg_match($pattern, $value, $m, \PREG_OFFSET_CAPTURE, $offset)) {
            $at = $m[0][1];
            // In a string when an odd count of unescaped quotes of a kind opens before it
            $quote = null;
            for ($i = 0; $i < $at; ++$i) {
                $char = $value[$i];
                if ('\\' === $char) {
                    ++$i;
                } elseif (null === $quote && ('"' === $char || "'" === $char)) {
                    $quote = $char;
                } elseif ($char === $quote) {
                    $quote = null;
                }
            }
            if (null === $quote) {
                return $at;
            }
            $offset = $at + 1;
        }

        return null;
    }

    /**
     * @internal
     */
    public static function closingParenthesis(string $value, int $open): int
    {
        $depth = 0;
        $length = \strlen($value);
        for ($i = $open; $i < $length; ++$i) {
            if ('(' === $value[$i]) {
                ++$depth;
            } elseif (')' === $value[$i] && 0 === --$depth) {
                return $i;
            }
        }

        throw new InvalidArgumentException(\sprintf('Missing ")" in "%s".', $value));
    }
}
