<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Render;

use Symfony\Bridge\TuiHtml\Css\Value;
use Symfony\Bridge\TuiHtml\Style\Color;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Style\TailwindStylesheet;
use Symfony\Component\HtmlRenderer\Css\ColorValue;

/** @internal */
final class TerminalColorPalette
{
    public static function lookupStandardName(string $value, string $scheme): ?array
    {
        // Additional aliases stay under the rendering context's explicit map, including its default foreground.
        $value = strtolower(trim($value));
        if (!preg_match('/^[a-z]+$/D', $value) || !\is_array(ColorValue::parse($value, $scheme))) {
            return null;
        }

        return self::lookup($value, $scheme);
    }

    public static function lookup(string $value, string $scheme): ?array
    {
        static $contexts = [];
        $colorScheme = 'dark' === $scheme ? ColorScheme::Dark : ColorScheme::Light;
        $media = $contexts[$colorScheme->value] ??= new MediaContext(80, 24, $colorScheme);
        $color = Value::opaqueColor($value, $media);
        if (!$color instanceof Color) {
            return null;
        }
        $rgb = $color->toRgb();

        return [[$rgb['r'] / 255.0, $rgb['g'] / 255.0, $rgb['b'] / 255.0], 1.0];
    }

    public static function get(): array
    {
        static $colors;
        if (null !== $colors) {
            return $colors;
        }
        $names = array_map(strval(...), range(0, 255));
        $names[] = 'default';
        foreach (['black', 'red', 'green', 'yellow', 'blue', 'magenta', 'cyan', 'white'] as $color) {
            $names[] = 'bright-'.$color;
            $names[] = 'bright_'.$color;
        }
        array_push($names, ...array_keys(TailwindStylesheet::getPaletteColors()));
        $colors = [];
        $media = new MediaContext();
        foreach ($names as $name) {
            if (($color = Value::color($name, $media)) instanceof Color) {
                $colors[$name] = $color->toHex();
            }
        }

        return $colors;
    }
}
