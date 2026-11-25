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
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Animation\Interpolation;

/**
 * Adapts shared animation definitions to terminal value interpolation.
 *
 * @internal
 */
final class Animation extends \Symfony\Component\HtmlRenderer\Animation\Animation
{
    private const STEPS = 32;

    public function interpolate(array $keyframes, float $progress, array $computed, MediaContext $media): array
    {
        return $this->valuesAt($keyframes, $progress, $computed, static fn (string $from, string $to, float $t): string => self::between($from, $to, $t, $media), static fn (string $underlying, string $value): string => self::add($underlying, $value, $media));
    }

    public static function between(string $from, string $to, float $t, MediaContext $media, ?string $property = null): string
    {
        if (null !== $property && \in_array($property, ['opacity', 'fill-opacity', 'stroke-opacity', 'flood-opacity', 'stop-opacity'], true)) {
            return Interpolation::between($from, $to, $t);
        }
        if (0.0 === $t || $from === $to) {
            return $from;
        }
        if (1.0 === $t) {
            return $to;
        }
        // visibility is visible between a visible end and a hidden one, like CSS
        if (\in_array([strtolower(trim($from)), strtolower(trim($to))], [['visible', 'hidden'], ['hidden', 'visible'], ['visible', 'collapse'], ['collapse', 'visible']], true)) {
            return $t > 0.0 && $t < 1.0 ? 'visible' : ($t <= 0.0 ? $from : $to);
        }
        if (($a = Value::color($from, $media)) instanceof Color && ($b = Value::color($to, $media)) instanceof Color) {
            // Colors stay between: an easing that overshoots, like cubic-bezier(.5, -1, .5, 2), does not take them further
            return $a->mix($b, (int) round(max(0.0, min(1.0, $t)) * 100))->toHex();
        }

        return self::combine($from, $to, $media, static fn (float $a, float $b, string $unit): string => self::format($a + ($b - $a) * $t).$unit, static fn (float $a, string $unitA, float $b, string $unitB): string => 'calc('.self::format($a).$unitA.' * '.self::format(1 - $t).' + '.self::format($b).$unitB.' * '.self::format($t).')', static fn (Color $a, Color $b): string => $a->mix($b, (int) round(max(0.0, min(1.0, $t)) * 100))->toHex()) ?? ($t < 0.5 ? $from : $to);
    }

    public static function add(string $underlying, string $value, MediaContext $media): string
    {
        return self::combine($underlying, $value, $media, static fn (float $a, float $b, string $unit): string => self::format($a + $b).$unit, static fn (float $a, string $unitA, float $b, string $unitB): string => 'calc('.self::format($a).$unitA.' + '.self::format($b).$unitB.')', static function (Color $a, Color $b): string {
            ['r' => $r, 'g' => $g, 'b' => $bl] = $a->toRgb();
            ['r' => $r2, 'g' => $g2, 'b' => $b2] = $b->toRgb();

            return Color::rgb(min(255, $r + $r2), min(255, $g + $g2), min(255, $bl + $b2))->toHex();
        }) ?? $value;
    }

    public static function isInterpolable(string $from, string $to, MediaContext $media): bool
    {
        if (Value::color($from, $media) instanceof Color && Value::color($to, $media) instanceof Color) {
            return true;
        }
        // Of the same shape, with something in between: equal ones are not
        $half = self::between($from, $to, 0.5, $media);

        return $half !== $from && $half !== $to;
    }

    public static function ease(array $timing, float $t): float
    {
        $value = \Symfony\Component\HtmlRenderer\Animation\Easing::evaluate($timing, $t);

        return 'steps' === $timing[0] ? $value : round($value * self::STEPS) / self::STEPS;
    }

    private static function combine(string $from, string $to, MediaContext $media, \Closure $numbers, ?\Closure $lengths, \Closure $colors): ?string
    {
        return Interpolation::combine($from, $to, $numbers, $lengths, $colors, static function (string $value) use ($media): ?Color {
            $color = Value::color($value, $media);

            return $color instanceof Color ? $color : null;
        });
    }

    private static function format(float $number): string
    {
        return Interpolation::format($number);
    }
}
