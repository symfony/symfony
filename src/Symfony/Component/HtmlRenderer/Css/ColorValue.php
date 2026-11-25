<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Css;

/** @internal */
final class ColorValue
{
    public const NAMED_COLORS = ['aliceblue' => '#f0f8ff', 'antiquewhite' => '#faebd7', 'aqua' => '#00ffff', 'aquamarine' => '#7fffd4', 'azure' => '#f0ffff', 'beige' => '#f5f5dc', 'bisque' => '#ffe4c4', 'blanchedalmond' => '#ffebcd', 'blueviolet' => '#8a2be2', 'brown' => '#a52a2a', 'burlywood' => '#deb887', 'cadetblue' => '#5f9ea0', 'chartreuse' => '#7fff00', 'chocolate' => '#d2691e', 'coral' => '#ff7f50', 'cornflowerblue' => '#6495ed', 'cornsilk' => '#fff8dc', 'crimson' => '#dc143c', 'darkblue' => '#00008b', 'darkcyan' => '#008b8b', 'darkgoldenrod' => '#b8860b', 'darkgray' => '#a9a9a9', 'darkgreen' => '#006400', 'darkgrey' => '#a9a9a9', 'darkkhaki' => '#bdb76b', 'darkmagenta' => '#8b008b', 'darkolivegreen' => '#556b2f', 'darkorange' => '#ff8c00', 'darkorchid' => '#9932cc', 'darkred' => '#8b0000', 'darksalmon' => '#e9967a', 'darkseagreen' => '#8fbc8f', 'darkslateblue' => '#483d8b', 'darkslategray' => '#2f4f4f', 'darkslategrey' => '#2f4f4f', 'darkturquoise' => '#00ced1', 'darkviolet' => '#9400d3', 'deeppink' => '#ff1493', 'deepskyblue' => '#00bfff', 'dimgray' => '#696969', 'dimgrey' => '#696969', 'dodgerblue' => '#1e90ff', 'firebrick' => '#b22222', 'floralwhite' => '#fffaf0', 'forestgreen' => '#228b22', 'fuchsia' => '#ff00ff', 'gainsboro' => '#dcdcdc', 'ghostwhite' => '#f8f8ff', 'gold' => '#ffd700', 'goldenrod' => '#daa520', 'greenyellow' => '#adff2f', 'honeydew' => '#f0fff0', 'hotpink' => '#ff69b4', 'indianred' => '#cd5c5c', 'indigo' => '#4b0082', 'ivory' => '#fffff0', 'khaki' => '#f0e68c', 'lavender' => '#e6e6fa', 'lavenderblush' => '#fff0f5', 'lawngreen' => '#7cfc00', 'lemonchiffon' => '#fffacd', 'lightblue' => '#add8e6', 'lightcoral' => '#f08080', 'lightcyan' => '#e0ffff', 'lightgoldenrodyellow' => '#fafad2', 'lightgray' => '#d3d3d3', 'lightgreen' => '#90ee90', 'lightgrey' => '#d3d3d3', 'lightpink' => '#ffb6c1', 'lightsalmon' => '#ffa07a', 'lightseagreen' => '#20b2aa', 'lightskyblue' => '#87cefa', 'lightslategray' => '#778899', 'lightslategrey' => '#778899', 'lightsteelblue' => '#b0c4de', 'lightyellow' => '#ffffe0', 'lime' => '#00ff00', 'limegreen' => '#32cd32', 'linen' => '#faf0e6', 'maroon' => '#800000', 'mediumaquamarine' => '#66cdaa', 'mediumblue' => '#0000cd', 'mediumorchid' => '#ba55d3', 'mediumpurple' => '#9370db', 'mediumseagreen' => '#3cb371', 'mediumslateblue' => '#7b68ee', 'mediumspringgreen' => '#00fa9a', 'mediumturquoise' => '#48d1cc', 'mediumvioletred' => '#c71585', 'midnightblue' => '#191970', 'mintcream' => '#f5fffa', 'mistyrose' => '#ffe4e1', 'moccasin' => '#ffe4b5', 'navajowhite' => '#ffdead', 'navy' => '#000080', 'oldlace' => '#fdf5e6', 'olive' => '#808000', 'olivedrab' => '#6b8e23', 'orange' => '#ffa500', 'orangered' => '#ff4500', 'orchid' => '#da70d6', 'palegoldenrod' => '#eee8aa', 'palegreen' => '#98fb98', 'paleturquoise' => '#afeeee', 'palevioletred' => '#db7093', 'papayawhip' => '#ffefd5', 'peachpuff' => '#ffdab9', 'peru' => '#cd853f', 'pink' => '#ffc0cb', 'plum' => '#dda0dd', 'powderblue' => '#b0e0e6', 'purple' => '#800080', 'rebeccapurple' => '#663399', 'rosybrown' => '#bc8f8f', 'royalblue' => '#4169e1', 'saddlebrown' => '#8b4513', 'salmon' => '#fa8072', 'sandybrown' => '#f4a460', 'seagreen' => '#2e8b57', 'seashell' => '#fff5ee', 'sienna' => '#a0522d', 'silver' => '#c0c0c0', 'skyblue' => '#87ceeb', 'slateblue' => '#6a5acd', 'slategray' => '#708090', 'slategrey' => '#708090', 'snow' => '#fffafa', 'springgreen' => '#00ff7f', 'steelblue' => '#4682b4', 'tan' => '#d2b48c', 'teal' => '#008080', 'thistle' => '#d8bfd8', 'tomato' => '#ff6347', 'turquoise' => '#40e0d0', 'violet' => '#ee82ee', 'wheat' => '#f5deb3', 'whitesmoke' => '#f5f5f5', 'yellowgreen' => '#9acd32'];

    public const SYSTEM_COLORS = [
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

    private const BASIC_COLORS = ['black' => '#000000', 'silver' => '#c0c0c0', 'gray' => '#808080', 'grey' => '#808080', 'white' => '#ffffff', 'maroon' => '#800000', 'red' => '#ff0000', 'purple' => '#800080', 'fuchsia' => '#ff00ff', 'magenta' => '#ff00ff', 'green' => '#008000', 'lime' => '#00ff00', 'olive' => '#808000', 'yellow' => '#ffff00', 'navy' => '#000080', 'blue' => '#0000ff', 'teal' => '#008080', 'aqua' => '#00ffff', 'cyan' => '#00ffff'];

    public static function accent(string $value, string $accent): string
    {
        return preg_replace_callback('/(?<![\\w-])accentcolor(text)?(?![\\w-])/i', static fn (array $match) => isset($match[1]) ? 'contrast-color('.$accent.')' : $accent, $value) ?? $value;
    }

    public static function parse(string $value, string $scheme = 'light', ?\Closure $fallback = null, int $depth = 0): array|false|null
    {
        if ($depth > 32 || \strlen($value) > 65536) {
            return false;
        }
        $value = strtolower(trim($value));
        $resolve = static fn (string $color) => self::parse($color, $scheme, $fallback, $depth + 1);
        if ('transparent' === $value) {
            return [[0.0, 0.0, 0.0], 0.0];
        }
        if (\in_array($value, ['none', 'initial'], true)) {
            return null;
        }
        if (preg_match('/^color-mix\((.+)\)$/s', $value, $m)) {
            return self::finite(self::mix($m[1], $resolve));
        }
        if (preg_match('/^contrast-color\((.+)\)$/s', $value, $m)) {
            return self::finite(self::contrast($m[1], $resolve));
        }
        if (preg_match('/^light-dark\((.+)\)$/s', $value, $m)) {
            $choices = Selector::splitTopLevel($m[1]);

            return 2 === \count($choices) ? $resolve($choices['dark' === $scheme ? 1 : 0]) : false;
        }
        if (null !== $function = ColorFunction::parse($value, $resolve)) {
            return self::finite($function);
        }
        if (preg_match('/^#([a-f0-9]{3}|[a-f0-9]{4}|[a-f0-9]{6}|[a-f0-9]{8})$/', $value, $m)) {
            $hex = $m[1];
            if (\strlen($hex) < 5) {
                $hex = implode('', array_map(static fn (string $digit) => $digit.$digit, str_split($hex)));
            }

            return [[hexdec(substr($hex, 0, 2)) / 255.0, hexdec(substr($hex, 2, 2)) / 255.0, hexdec(substr($hex, 4, 2)) / 255.0], 8 === \strlen($hex) ? hexdec(substr($hex, 6, 2)) / 255.0 : 1.0];
        }
        if (isset(self::SYSTEM_COLORS[$value])) {
            return $resolve(self::SYSTEM_COLORS[$value]['dark' === $scheme ? 1 : 0]);
        }
        if (null !== $fallback && \is_array($custom = $fallback($value))) {
            return $custom;
        }
        $named = self::BASIC_COLORS[$value] ?? self::NAMED_COLORS[$value] ?? null;

        return null !== $named ? $resolve($named) : false;
    }

    private static function finite(array|false|null $value): array|false|null
    {
        if (\is_array($value) && (!is_finite($value[1]) || array_any($value[0], static fn (float $channel) => !is_finite($channel)))) {
            return false;
        }

        return $value;
    }

    public static function stringify(array $color): string
    {
        [$channels, $alpha] = $color;
        $channels = ColorSpace::mapToSrgbGamut($channels);
        [$red, $green, $blue] = array_map(static fn (float $channel): int => (int) round(max(0.0, min(1.0, $channel)) * 255), $channels);
        if ($alpha >= 1.0) {
            return \sprintf('#%02x%02x%02x', $red, $green, $blue);
        }

        return \sprintf('rgba(%d, %d, %d, %s)', $red, $green, $blue, rtrim(rtrim(\sprintf('%.6F', max(0.0, min(1.0, $alpha))), '0'), '.') ?: '0');
    }

    public static function interpolationMethod(string $value): ?array
    {
        if (!preg_match('/^in\s+([a-z0-9-]+)(?:\s+(shorter|longer|increasing|decreasing)\s+hue)?$/', strtolower(trim($value)), $m)) {
            return null;
        }
        if (!\in_array($m[1], [...ColorSpace::PREDEFINED, ...ColorSpace::POLAR, 'lab', 'oklab'], true) || '' !== ($m[2] ?? '') && !\in_array($m[1], ColorSpace::POLAR, true)) {
            return null;
        }

        return [$m[1], $m[2] ?? '' ?: 'shorter'];
    }

    public static function mix(string $arguments, \Closure $resolve): array|false
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
        if (!\is_array($first = $resolve($first)) || !\is_array($second = $resolve($second))) {
            return false;
        }
        [$srgb, $alpha] = ColorSpace::interpolate($space, $hueMethod, $first, $second, $secondPercentage / $sum);

        // Percentages summing to less than 100% make the mix translucent
        return [$srgb, $alpha * min(1.0, $sum / 100)];
    }

    public static function contrast(string $argument, \Closure $resolve): array|false
    {
        if (!\is_array($srgba = $resolve($argument))) {
            return false;
        }
        // The WCAG relative luminance: white contrasts more below about 0.179, where both contrast ratios are equal
        $linear = static fn (float $c): float => ($c = max(0.0, min(1.0, $c))) <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        [$r, $g, $b] = $srgba[0];
        $luminance = 0.2126 * $linear($r) + 0.7151999999999999 * $linear($g) + 0.0722 * $linear($b);
        $white = 1.05 / ($luminance + 0.05);
        $black = ($luminance + 0.05) / 0.05;

        return [$white >= $black ? [1.0, 1.0, 1.0] : [0.0, 0.0, 0.0], 1.0];
    }

    public static function colorStop(string $stop): array
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
}
