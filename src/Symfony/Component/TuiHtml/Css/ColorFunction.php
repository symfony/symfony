<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Css;

use Symfony\Component\TuiHtml\Style\Color;
use Symfony\Component\TuiHtml\Style\MediaContext;

/**
 * Reads the CSS color functions: rgb(), hsl(), hwb(), lab(), lch(), oklab(), oklch() and color().
 *
 * Channels are computed in extended sRGB, then mapped into its gamut and rounded to a true color.
 *
 * @experimental
 *
 * @internal
 */
final class ColorFunction
{
    private const NUMBER = '[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?';
    /** What 100% is for each channel of a function; null for a hue */
    private const PERCENTAGES = ['rgb' => [255, 255, 255], 'hsl' => [null, 100, 100], 'hwb' => [null, 100, 100], 'lab' => [100, 125, 125], 'lch' => [100, 150, null], 'oklab' => [1, 0.4, 0.4], 'oklch' => [1, 0.4, null], 'color' => [1, 1, 1]];

    /**
     * @param (\Closure(string): (array{list<float>, float}|false|null))|null $resolve Reads the origin of a relative color, like rgb(from red r g b)
     *
     * @return array{list<float>, float}|false|null The extended sRGB channels and the alpha, false when invalid, null when the value is not a color function
     */
    public static function parse(string $value, ?\Closure $resolve = null): array|false|null
    {
        if (!preg_match('/^([a-z]+)\((.*)\)$/s', strtolower(trim($value)), $m)) {
            return null;
        }
        $function = match ($m[1]) {
            'rgba' => 'rgb',
            'hsla' => 'hsl',
            default => $m[1],
        };
        if (!isset(self::PERCENTAGES[$function])) {
            return null;
        }
        $arguments = $m[2];
        $space = $function;
        if (str_starts_with(ltrim($arguments), 'from ')) {
            return null === $resolve ? false : self::relative($function, $arguments, $resolve);
        }
        if ('color' === $function) {
            if (!preg_match('/^\s*([a-z0-9-]+)\s+(.*)$/s', $arguments, $c) || !\in_array($c[1], ColorSpace::PREDEFINED, true)) {
                return false;
            }
            [, $space, $arguments] = $c;
        }
        if (null === $arguments = self::arguments($arguments, \in_array($function, ['rgb', 'hsl'], true))) {
            return false;
        }
        [$channels, $alpha] = $arguments;
        if (false === $alpha = null === $alpha ? 1.0 : self::alpha($alpha)) {
            return false;
        }
        $values = [];
        foreach ($channels as $i => $channel) {
            $hundredPercent = self::PERCENTAGES[$function][$i];
            if (false === $values[] = null === $hundredPercent ? self::angle($channel) : self::channel($channel, $hundredPercent)) {
                return false;
            }
        }

        return [ColorSpace::toSrgb('rgb' === $space ? 'srgb' : $space, self::normalize($space, $values)), $alpha];
    }

    /**
     * Maps extended sRGB channels into the gamut, then rounds them to a true color.
     *
     * @param list<float> $srgb
     */
    public static function toColor(array $srgb): Color
    {
        $channel = static fn (float $c): int => (int) round(255 * max(0.0, min(1.0, $c)));
        $srgb = ColorSpace::mapToSrgbGamut($srgb);

        return Color::rgb($channel($srgb[0]), $channel($srgb[1]), $channel($srgb[2]));
    }

    /**
     * Reads an angle in degrees: deg, grad, rad, turn or a number of degrees.
     */
    public static function angle(string $value): float|false
    {
        $value = strtolower(trim($value));
        if ('none' === $value) {
            return 0.0;
        }
        // A math function over angles and numbers of degrees
        if (Length::isMath($value)) {
            return Length::angle($value) ?? false;
        }
        if (!preg_match('/^('.self::NUMBER.')(deg|grad|rad|turn)?$/', $value, $m)) {
            return false;
        }

        return match ($m[2] ?? '') {
            'grad' => (float) $m[1] * 0.9,
            'rad' => rad2deg((float) $m[1]),
            'turn' => (float) $m[1] * 360,
            default => (float) $m[1],
        };
    }

    /**
     * Reads a channel: a number, or a percentage of what 100% is; none is 0.
     */
    public static function channel(string $value, float $hundredPercent): float|false
    {
        $value = strtolower(trim($value));
        if ('none' === $value) {
            return 0.0;
        }
        // A math function over numbers, or over percentages of what 100% is
        if (Length::isMath($value)) {
            if (!str_contains($value, '%')) {
                return Length::number($value) ?? false;
            }
            $linear = Length::linear($value, new MediaContext());

            return null === $linear || 0.0 !== $linear[1] ? false : $linear[0] / 100 * $hundredPercent;
        }
        if (!preg_match('/^('.self::NUMBER.')(%?)$/', $value, $m)) {
            return false;
        }

        return '%' === $m[2] ? (float) $m[1] / 100 * $hundredPercent : (float) $m[1];
    }

    /**
     * Reads a relative color: the channels of the origin, converted to the space of the function, are keywords its channels can use, like oklch(from var(--brand) calc(l + 0.1) c h).
     *
     * @param \Closure(string): (array{list<float>, float}|false|null) $resolve
     *
     * @return array{list<float>, float}|false
     */
    private static function relative(string $function, string $arguments, \Closure $resolve): array|false
    {
        $tokens = Value::split(str_replace('/', ' / ', $arguments));
        array_shift($tokens);
        if (!\is_array($origin = $resolve(array_shift($tokens) ?? ''))) {
            return false;
        }
        $space = 'rgb' === $function ? 'srgb' : $function;
        if ('color' === $function && !\in_array($space = array_shift($tokens) ?? '', ColorSpace::PREDEFINED, true)) {
            return false;
        }
        if (null === $arguments = self::arguments(implode(' ', $tokens), false)) {
            return false;
        }
        [$channels, $alpha] = $arguments;
        $values = ColorSpace::fromSrgb($space, $origin[0]);
        if ('rgb' === $function) {
            $values = array_map(static fn (float $c) => $c * 255, $values);
        }
        $names = match ($function) {
            'rgb' => ['r', 'g', 'b'],
            'hsl' => ['h', 's', 'l'],
            'hwb' => ['h', 'w', 'b'],
            'lab', 'oklab' => ['l', 'a', 'b'],
            'lch', 'oklch' => ['l', 'c', 'h'],
            default => str_starts_with($space, 'xyz') ? ['x', 'y', 'z'] : ['r', 'g', 'b'],
        };
        $keywords = array_combine($names, $values) + ['alpha' => $origin[1]];
        $resolved = [];
        foreach ($channels as $i => $channel) {
            $hundredPercent = self::PERCENTAGES[$function][$i];
            if (false === $resolved[] = self::relativeChannel($channel, $keywords, $hundredPercent)) {
                return false;
            }
        }
        $alpha = null === $alpha ? $origin[1] : self::relativeChannel($alpha, $keywords, 1);
        if (false === $alpha) {
            return false;
        }

        return [ColorSpace::toSrgb($space, self::normalize($function, $resolved)), max(0.0, min(1.0, $alpha))];
    }

    /**
     * @param array<string, float> $keywords
     * @param float|int|null       $hundredPercent What 100% is, null for a hue
     */
    private static function relativeChannel(string $channel, array $keywords, float|int|null $hundredPercent): float|false
    {
        $channel = strtolower(trim($channel));
        if (isset($keywords[$channel])) {
            return $keywords[$channel];
        }
        if (Length::isMath($channel)) {
            $expression = preg_replace_callback('/(?<![a-z-])([a-z]+)(?![a-z(-])/', static fn (array $m) => isset($keywords[$m[1]]) ? '('.\sprintf('%.10F', $keywords[$m[1]]).')' : $m[1], $channel);

            return (null === $hundredPercent ? Length::angle($expression) : Length::number($expression)) ?? false;
        }

        return null === $hundredPercent ? self::angle($channel) : self::channel($channel, $hundredPercent);
    }

    /**
     * Splits the arguments of a color function: three channels, then the alpha after a slash, or a fourth comma in the legacy syntax.
     *
     * @return array{list<string>, string|null}|null
     */
    private static function arguments(string $arguments, bool $legacy): ?array
    {
        $commas = Selector::splitTopLevel($arguments);
        if (\count($commas) > 1) {
            if (!$legacy || !\in_array(\count($commas), [3, 4], true)) {
                return null;
            }

            return [\array_slice($commas, 0, 3), $commas[3] ?? null];
        }
        $slashes = Selector::splitTopLevel($arguments, '/');
        if (\count($slashes) > 2 || 3 !== \count($channels = Value::split($slashes[0] ?? ''))) {
            return null;
        }

        return [$channels, $slashes[1] ?? null];
    }

    private static function alpha(string $value): float|false
    {
        $alpha = self::channel($value, 1);

        return false === $alpha ? false : max(0.0, min(1.0, $alpha));
    }

    /**
     * Clamps the channels CSS clamps when the color is read.
     *
     * @param list<float> $channels
     *
     * @return list<float>
     */
    private static function normalize(string $space, array $channels): array
    {
        return match ($space) {
            // rgb() is clamped to its gamut, unlike color(srgb ...)
            'rgb' => array_map(static fn (float $c) => max(0.0, min(255.0, $c)) / 255, $channels),
            'lab', 'lch' => [max(0.0, min(100.0, $channels[0])), ...\array_slice($channels, 1)],
            'oklab', 'oklch' => [max(0.0, min(1.0, $channels[0])), ...\array_slice($channels, 1)],
            default => $channels,
        };
    }
}
