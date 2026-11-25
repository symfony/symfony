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
final class Anchor
{
    /**
     * @return list<string>
     */
    public static function references(array $style): array
    {
        $names = [];
        if (str_starts_with($name = trim($style['position-anchor'] ?? ''), '--')) {
            $names[$name] = true;
        }
        foreach (['width', 'height', 'min-width', 'max-width', 'min-height', 'max-height', 'top', 'right', 'bottom', 'left'] as $property) {
            $value = $style[$property] ?? '';
            $parsed = \in_array($property, ['top', 'right', 'bottom', 'left'], true) ? self::edge($value) : self::sizeFunction($value, str_ends_with($property, 'height'));
            if (null !== ($name = $parsed[0] ?? null)) {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    public static function applyFallback(array $style, array $override): array
    {
        if (array_any(['inset', 'top', 'right', 'bottom', 'left', 'position-area'], static fn (string $property): bool => isset($override[$property]))) {
            $style = array_diff_key($style, array_flip(['inset', 'top', 'right', 'bottom', 'left']));
        }

        return $override + $style;
    }

    /**
     * @return list<array{array<string, string>, list<string>}>
     */
    public static function alternatives(array $style, array $rules, int $limit = 100000): array
    {
        $value = trim($style['position-try-fallbacks'] ?? self::order($style['position-try'] ?? '')[1]);
        if ('' === $value || 'none' === strtolower($value)) {
            return [];
        }
        $alternatives = [];
        foreach (Selector::splitTopLevel($value) as $fallback) {
            if (null === $parsed = self::fallback($fallback)) {
                continue;
            }
            [$name, $tactics, $area] = $parsed;
            if (null !== $name && !isset($rules[$name]) || null === $name && null === $area) {
                continue;
            }
            if (\count($alternatives) >= $limit) {
                throw new \LengthException('Position alternatives exceed the document box limit.');
            }
            $declarations = [];
            foreach (null !== $name ? $rules[$name] : ['position-area' => $area] as $property => $value) {
                foreach (DeclarationExpander::expand(new Declaration($property, $value)) as $declaration) {
                    $physical = LogicalProperties::physical($declaration->property, $style['writing-mode'] ?? 'horizontal-tb', $style['direction'] ?? 'ltr');
                    if (\in_array($physical, ['position-area', 'position-anchor', 'top', 'right', 'bottom', 'left', 'width', 'height'], true)) {
                        $declarations[$physical] = $declaration->value;
                    }
                }
            }
            $alternatives[] = [$declarations, $tactics];
        }

        return $alternatives;
    }

    /**
     * @return array{?string, string, int, int, ?string}|null Name, dimension, call bounds and fallback
     */
    public static function sizeFunction(string $value, bool $vertical): ?array
    {
        $value = trim($value);
        if (1 !== preg_match_all('/(?<![\w-])anchor-size\(/i', $value, $all, \PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $start = $all[0][0][1];
        $end = ValueTokenizer::closingParenthesis($value, $start + 11);
        $arguments = Selector::splitTopLevel(substr($value, $start + 12, $end - $start - 12));
        $parts = ValueTokenizer::split(trim($arguments[0] ?? ''));
        $name = isset($parts[0]) && str_starts_with($parts[0], '--') ? array_shift($parts) : null;
        $dimension = match (isset($parts[0]) ? strtolower($parts[0]) : null) {
            null => $vertical ? 'height' : 'width',
            'width', 'inline', 'self-inline' => 'width',
            'height', 'block', 'self-block' => 'height',
            default => null,
        };
        if (null === $dimension || \count($parts) > 1) {
            return null;
        }
        $fallback = trim(implode(', ', \array_slice($arguments, 1)));

        return [$name, $dimension, $start, $end, '' === $fallback ? null : $fallback];
    }

    public static function edge(string $value): ?array
    {
        if (!preg_match('/^anchor\(\s*(--[a-z_][\w-]*\s+)?(top|right|bottom|left|center|start|end)\s*\)$/i', trim($value), $m)) {
            return null;
        }
        $edge = match (strtolower($m[2])) {
            'start', 'left' => 'left',
            'end', 'right' => 'right',
            'top' => 'top',
            'bottom' => 'bottom',
            default => 'center',
        };

        return ['' !== trim($m[1]) ? trim($m[1]) : null, $edge];
    }

    public static function fallback(string $fallback): ?array
    {
        $fallback = trim($fallback);
        if (null !== PositionArea::parse($fallback)) {
            return [null, [], $fallback];
        }
        $name = null;
        $tactics = [];
        foreach (ValueTokenizer::split($fallback) as $part) {
            if (str_starts_with($part, '--') && null === $name && preg_match('/^--[a-z_][\w-]*$/i', $part)) {
                $name = $part;
            } elseif (\in_array($part, ['flip-block', 'flip-inline'], true)) {
                $tactics[] = $part;
            } else {
                return null;
            }
        }

        return null === $name && !$tactics ? null : [$name, $tactics, null];
    }

    public static function order(string $value): array
    {
        if (!preg_match('/^\s*(normal|most-width|most-height|most-inline-size|most-block-size)(?![\w-])\s*(.*)$/is', $value, $m)) {
            return [null, trim($value)];
        }
        $order = match (strtolower($m[1])) {
            'most-width', 'most-inline-size' => 'most-width',
            'most-height', 'most-block-size' => 'most-height',
            default => 'normal',
        };

        return [$order, trim($m[2])];
    }
}
