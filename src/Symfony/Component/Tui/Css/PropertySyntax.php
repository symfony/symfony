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

use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\MediaContext;

/**
 * The syntax of a custom property registered by @property, like "<color>" or "auto | <length>+".
 *
 * Alternatives are separated by "|"; each is a keyword or a type, a "+"
 * after it for a list separated by spaces, a "#" for one separated by
 * commas. The types are <color>, <length>, <percentage>,
 * <length-percentage>, <number>, <integer>, <angle>, <time>, <resolution>,
 * <custom-ident>, <string>, <url>, <image> and <transform-function>, the
 * last three loosely.
 *
 * @internal
 */
final class PropertySyntax
{
    public static function matches(string $syntax, string $value, MediaContext $media): bool
    {
        $syntax = trim($syntax, " \t\n\"'");
        $value = trim($value);
        if ('*' === $syntax) {
            return true;
        }
        if ('' === $value) {
            return false;
        }
        foreach (explode('|', $syntax) as $alternative) {
            $alternative = trim($alternative);
            $multiplier = substr($alternative, -1);
            if ('+' === $multiplier || '#' === $multiplier) {
                $component = substr($alternative, 0, -1);
                $parts = '#' === $multiplier ? array_map('trim', Selector::splitTopLevel($value)) : Value::split($value);
                if ($parts && array_all($parts, static fn (string $part) => self::component($component, $part, $media))) {
                    return true;
                }
            } elseif (self::component($alternative, $value, $media)) {
                return true;
            }
        }

        return false;
    }

    private static function component(string $component, string $value, MediaContext $media): bool
    {
        $lower = strtolower($value);
        $number = '[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?';

        return match ($component) {
            // A color whatever its alpha, a transparent one like the #0000 of the registered properties of Tailwind too
            '<color>' => Value::opaqueColor($value, $media) instanceof Color || 'transparent' === $lower || 'currentcolor' === $lower,
            '<length>' => '0' === $value || 1 === preg_match('/^'.$number.'(?:px|ch|em|rem|ex|lh|rlh|vw|vh|vmin|vmax|cm|mm|in|pt|pc|q|cap|ic)$/i', $value) || self::isCalc($lower),
            '<percentage>' => 1 === preg_match('/^'.$number.'%$/', $value) || self::isCalc($lower),
            '<length-percentage>' => self::component('<length>', $value, $media) || self::component('<percentage>', $value, $media),
            '<number>' => 1 === preg_match('/^'.$number.'$/i', $value) || self::isCalc($lower),
            '<integer>' => 1 === preg_match('/^[+-]?\d+$/', $value) || self::isCalc($lower),
            '<angle>' => 1 === preg_match('/^'.$number.'(?:deg|rad|grad|turn)$/i', $value) || self::isCalc($lower),
            '<time>' => 1 === preg_match('/^'.$number.'m?s$/i', $value) || self::isCalc($lower),
            '<resolution>' => 1 === preg_match('/^'.$number.'(?:dpi|dpcm|dppx|x)$/i', $value),
            '<custom-ident>' => 1 === preg_match('/^-?[a-z_][\w-]*$/i', $value) && !\in_array($lower, ['initial', 'inherit', 'unset', 'revert', 'revert-layer', 'default'], true),
            '<string>' => 1 === preg_match('/^(["\']).*\1$/s', $value),
            '<url>' => str_starts_with($lower, 'url(') && str_ends_with($value, ')'),
            '<image>' => str_starts_with($lower, 'url(') || 1 === preg_match('/^(?:repeating-)?(?:linear|radial|conic)-gradient\(/', $lower),
            '<transform-function>' => 1 === preg_match('/^[a-z0-9]+\(.*\)$/is', $lower),
            // A keyword stands for itself
            default => !str_starts_with($component, '<') && $lower === strtolower($component),
        };
    }

    private static function isCalc(string $value): bool
    {
        return Length::isMath($value);
    }
}
