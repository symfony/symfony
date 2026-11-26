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
use Symfony\Component\HtmlRenderer\Css\ColorSpace;

/** @internal */
final class ColorFunction
{
    public static function parse(string $value, ?\Closure $resolve = null): array|false|null
    {
        return \Symfony\Component\HtmlRenderer\Css\ColorFunction::parse($value, $resolve);
    }

    public static function angle(string $value): float|false
    {
        return \Symfony\Component\HtmlRenderer\Css\ColorFunction::angle($value);
    }

    public static function channel(string $value, float $hundredPercent): float|false
    {
        return \Symfony\Component\HtmlRenderer\Css\ColorFunction::channel($value, $hundredPercent);
    }

    public static function toColor(array $srgb): Color
    {
        $channel = static fn (float $c): int => (int) round(255 * max(0.0, min(1.0, $c)));
        $srgb = ColorSpace::mapToSrgbGamut($srgb);

        return Color::rgb($channel($srgb[0]), $channel($srgb[1]), $channel($srgb[2]));
    }
}
