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
final class BackgroundParser
{
    public static function components(string $value): array
    {
        $components = [];
        $defaults = ['background-image' => 'none', 'background-position' => '0% 0%', 'background-size' => 'auto', 'background-repeat' => 'repeat', 'background-origin' => 'padding-box', 'background-clip' => 'border-box', 'background-attachment' => 'scroll'];
        foreach (Selector::splitTopLevel($value) as $layer) {
            foreach (self::layer($layer) + $defaults as $property => $part) {
                $components[$property][] = $part;
            }
        }

        return array_map(static fn (array $parts): string => implode(', ', $parts), $components);
    }

    public static function layer(string $value, int $index = 0, ?\Closure $length = null): array
    {
        $layer = Selector::splitTopLevel($value)[$index] ?? '';
        $parts = [];
        $position = $size = $repeat = $boxes = [];
        $sizing = false;
        foreach (self::tokens($layer) as $token) {
            $lower = strtolower($token);
            if ('/' === $token) {
                $sizing = true;
            } elseif (preg_match('/^(?:url|image-set|-webkit-image-set|(?:repeating-)?(?:linear|radial|conic)-gradient)\(/i', $token)) {
                $parts['background-image'] = $token;
            } elseif ('none' === $lower) {
                $parts['background-image'] = 'none';
            } elseif (\in_array($lower, ['border-box', 'padding-box', 'content-box', 'text'], true)) {
                $boxes[] = $lower;
            } elseif (\in_array($lower, ['repeat', 'no-repeat', 'space', 'round', 'repeat-x', 'repeat-y'], true)) {
                $repeat[] = $lower;
            } elseif (\in_array($lower, ['scroll', 'fixed', 'local'], true)) {
                $parts['background-attachment'] = $lower;
            } elseif ($sizing && (\in_array($lower, ['auto', 'cover', 'contain'], true) || (null === $length ? self::isLength($token) : null !== GradientParser::backgroundLength($token, $length, false)))) {
                $size[] = $token;
            } elseif (!$sizing && (\in_array($lower, ['left', 'center', 'right', 'top', 'bottom'], true) || (null === $length ? self::isLength($token) : null !== GradientParser::backgroundLength($token, $length, false)))) {
                $position[] = $token;
            }
        }
        if ($position) {
            $parts['background-position'] = implode(' ', $position);
        }
        if ($size) {
            $parts['background-size'] = implode(' ', $size);
        }
        if ($repeat) {
            $parts['background-repeat'] = implode(' ', $repeat);
        }
        if ($boxes) {
            if ('text' !== $boxes[0]) {
                $parts['background-origin'] = $boxes[0];
            }
            $parts['background-clip'] = $boxes[1] ?? $boxes[0];
        }

        return $parts;
    }

    /** @param \Closure(string, bool): ?float $length */
    public static function part(string $property, string $value, \Closure $length, int $index = 0): string|array|null
    {
        $layers = Selector::splitTopLevel($value);
        $tokens = ValueTokenizer::split(trim($layers[$index % max(1, \count($layers))] ?? ''));
        $lower = array_map(strtolower(...), $tokens);
        switch ($property) {
            case 'background-clip':
                return 1 === \count($lower) && \in_array($lower[0], ['border-box', 'padding-box', 'content-box', 'text'], true) ? $lower[0] : null;
            case 'background-origin':
                return 1 === \count($lower) && \in_array($lower[0], ['border-box', 'padding-box', 'content-box'], true) ? $lower[0] : null;
            case 'background-size':
                if (['cover'] === $lower || ['contain'] === $lower) {
                    return [null, null, $lower[0]];
                }
                if (!$tokens || \count($tokens) > 2) {
                    return null;
                }
                $size = [];
                foreach ($tokens as $axis => $token) {
                    if ('auto' === strtolower($token)) {
                        $size[] = null;
                    } elseif (null === $resolved = GradientParser::backgroundLength($token, $length, 1 === $axis)) {
                        return null;
                    } elseif ($resolved['percent'] < 0 || $resolved['offset'] < 0) {
                        return null;
                    } else {
                        $size[] = $resolved;
                    }
                }

                return [$size[0], $size[1] ?? null];
            case 'background-repeat':
                return match (true) {
                    ['repeat-x'] === $lower => ['repeat', 'no-repeat'],
                    ['repeat-y'] === $lower => ['no-repeat', 'repeat'],
                    1 === \count($lower) && self::isRepeat($lower[0]) => [$lower[0], $lower[0]],
                    2 === \count($lower) && self::isRepeat($lower[0]) && self::isRepeat($lower[1]) => [$lower[0], $lower[1]],
                    default => null,
                };
            case 'background-repeat-x':
            case 'background-repeat-y':
                return 1 === \count($lower) && self::isRepeat($lower[0]) ? $lower[0] : null;
            case 'background-position-x':
            case 'background-position-y':
                return GradientParser::axis($tokens, 'background-position-y' === $property, $length);
            case 'background-position':
                return GradientParser::position($tokens, $length);
        }

        return null;
    }

    /** A slash is a layout separator only outside functions, strings and escapes. */
    public static function tokens(string $value): array
    {
        $out = '';
        $depth = 0;
        $quote = null;
        for ($index = 0, $size = \strlen($value); $index < $size; ++$index) {
            $char = $value[$index];
            if ('\\' === $char) {
                $out .= $char.($value[++$index] ?? '');
                continue;
            }
            if (null !== $quote) {
                if ($char === $quote) {
                    $quote = null;
                }
            } elseif ('"' === $char || "'" === $char) {
                $quote = $char;
            } elseif ('(' === $char) {
                ++$depth;
            } elseif (')' === $char) {
                --$depth;
            } elseif ('/' === $char && 0 === $depth) {
                $out .= ' / ';
                continue;
            }
            $out .= $char;
        }

        return ValueTokenizer::split($out);
    }

    private static function isLength(string $value): bool
    {
        return (bool) preg_match('/^[+-]?(?:\d*\.)?\d+(?:[a-z]+|%)?$/iD', $value) || (bool) preg_match('/^(?:calc|min|max|clamp|round|mod|rem|abs|sign|hypot|pow|sqrt|exp|log|sin|cos|tan|asin|acos|atan|atan2)\(/i', $value);
    }

    private static function isRepeat(string $value): bool
    {
        return \in_array($value, ['repeat', 'no-repeat', 'space', 'round'], true);
    }
}
