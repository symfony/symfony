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
final class DeclarationExpander
{
    public const SIDE_SHORTHANDS = ['margin-top' => ['margin', 0], 'margin-right' => ['margin', 1], 'margin-bottom' => ['margin', 2], 'margin-left' => ['margin', 3], 'padding-top' => ['padding', 0], 'padding-right' => ['padding', 1], 'padding-bottom' => ['padding', 2], 'padding-left' => ['padding', 3], 'top' => ['inset', 0], 'right' => ['inset', 1], 'bottom' => ['inset', 2], 'left' => ['inset', 3], 'border-top-width' => ['border-width', 0], 'border-right-width' => ['border-width', 1], 'border-bottom-width' => ['border-width', 2], 'border-left-width' => ['border-width', 3], 'border-top-style' => ['border-style', 0], 'border-right-style' => ['border-style', 1], 'border-bottom-style' => ['border-style', 2], 'border-left-style' => ['border-style', 3], 'border-top-color' => ['border-color', 0], 'border-right-color' => ['border-color', 1], 'border-bottom-color' => ['border-color', 2], 'border-left-color' => ['border-color', 3], 'border-top-left-radius' => ['border-radius', 0], 'border-top-right-radius' => ['border-radius', 1], 'border-bottom-right-radius' => ['border-radius', 2], 'border-bottom-left-radius' => ['border-radius', 3]];

    public static function expand(Declaration $declaration, ?\Closure $colorValidator = null): array
    {
        $property = $declaration->property;
        $value = trim($declaration->value);
        if (str_starts_with($property, '--')) {
            return [$declaration];
        }
        $pending = 1 === preg_match('/(?:var|env|if|attr|sibling-index|sibling-count|--[\w-]+)\(/i', $value);
        $wide = \in_array(strtolower($value), ['inherit', 'initial', 'unset', 'revert', 'revert-layer'], true);
        if (str_starts_with($property, 'overscroll-behavior') && !$pending) {
            $value = strtolower($value);
        }
        $values = [$property => $value];
        $sideProperties = array_filter(self::SIDE_SHORTHANDS, static fn (array $source) => $source[0] === $property);
        if ($sideProperties) {
            $parts = ValueTokenizer::split($value);
            if (!$pending && !$wide && (\count($parts) < 1 || 'border-radius' !== $property && \count($parts) > 4 || !self::validSides($property, $parts, $colorValidator))) {
                return [];
            }
            foreach ($sideProperties as $side => $_) {
                $values[$side] = $pending || $wide ? $value : self::parentValue([$property => $value], $side);
            }
        } elseif (preg_match('/^(margin|padding|inset|scroll-margin|scroll-padding)-(inline|block)$/', $property)) {
            $parts = ValueTokenizer::split($value);
            if (!$pending && !$wide && (\count($parts) < 1 || \count($parts) > 2)) {
                return [];
            }
            $values[$property.'-start'] = $pending || $wide ? $value : $parts[0];
            $values[$property.'-end'] = $pending || $wide ? $value : ($parts[1] ?? $parts[0]);
        } elseif (preg_match('/^border(?:-(top|right|bottom|left))?$/', $property, $m)) {
            $border = $pending || $wide ? ['width' => $value, 'style' => $value, 'color' => $value] : self::border($value, $colorValidator);
            if (null === $border) {
                return [];
            }
            foreach (isset($m[1]) ? [$m[1]] : ['top', 'right', 'bottom', 'left'] as $side) {
                foreach ($border as $part => $component) {
                    $values['border-'.$side.'-'.$part] = $component;
                }
            }
            if (!isset($m[1])) {
                foreach ($border as $part => $component) {
                    $values['border-'.$part] = $component;
                }
            }
        } elseif ('outline' === $property) {
            $parts = ValueTokenizer::split($value);
            $auto = 'auto' === OutlineParser::components($value, static fn (string $part): bool => self::isLength($part, false, true))['style'];
            $outline = $pending || $wide ? ['width' => $value, 'style' => $value, 'color' => $value] : self::border(implode(' ', array_map(static fn (string $part): string => 'auto' === strtolower($part) ? 'solid' : $part, $parts)), $colorValidator);
            if (null === $outline) {
                return [];
            }
            if ($auto && !$pending && !$wide) {
                $outline['style'] = 'auto';
            }
            foreach ($outline as $part => $component) {
                $values['outline-'.$part] = $component;
            }
        } elseif ('marker' === $property) {
            foreach (['start', 'mid', 'end'] as $part) {
                $values['marker-'.$part] = $value;
            }
        } elseif ('white-space' === $property) {
            $components = $pending || $wide ? [$value, $value] : WhiteSpace::components($value);
            if (null === $components) {
                return [];
            }
            $values['white-space-collapse'] = $components[0];
            $values['text-wrap-mode'] = $components[1];
        } elseif ('text-wrap' === $property) {
            $parts = ValueTokenizer::split(strtolower($value));
            if (!$pending && !$wide && (!$parts || array_diff($parts, ['wrap', 'nowrap', 'balance', 'pretty', 'stable']))) {
                return [];
            }
            $values['text-wrap-mode'] = $pending || $wide ? $value : (\in_array('nowrap', $parts, true) ? 'nowrap' : 'wrap');
        } elseif ('text-emphasis' === $property) {
            $values += TextEmphasis::parse($value);
        } elseif (\in_array($property, ['text-decoration', '-webkit-text-decoration'], true)) {
            $decoration = $pending || $wide ? array_fill_keys(['line', 'style', 'color', 'thickness'], $value) : TextDecoration::parse($value, $colorValidator);
            if (null === $decoration) {
                return [];
            }
            $decoration += ['line' => 'none', 'style' => 'solid', 'color' => 'currentcolor', 'thickness' => 'auto'];
            foreach ($decoration as $part => $component) {
                $values['text-decoration-'.$part] = $component;
            }
        } elseif ('border-image' === $property) {
            $source = $pending || $wide ? $value : ImageSource::border($value);
            if (null === $source) {
                return [];
            }
            $values['border-image-source'] = $source;
        } elseif ('flex' === $property) {
            $flex = $pending || $wide ? [$value, $value, $value] : self::flex($value);
            if (null === $flex) {
                return [];
            }
            $values += array_combine(['flex-grow', 'flex-shrink', 'flex-basis'], $flex);
        } elseif (\in_array($property, ['gap', 'overflow', 'overscroll-behavior', 'place-items', 'place-content'], true)) {
            $parts = ValueTokenizer::split($value);
            if (!$pending && !$wide && (\count($parts) < 1 || \count($parts) > 2)) {
                return [];
            }
            $first = $pending || $wide ? $value : $parts[0];
            $second = $pending || $wide ? $value : ($parts[1] ?? $parts[0]);
            $names = match ($property) {
                'gap' => ['row-gap', 'column-gap'],
                'overflow' => ['overflow-x', 'overflow-y'],
                'overscroll-behavior' => ['overscroll-behavior-x', 'overscroll-behavior-y'],
                'place-items' => ['align-items', 'justify-items'],
                default => ['align-content', 'justify-content'],
            };
            if (!$pending && !$wide && ('gap' === $property && array_any($parts, static fn (string $part) => 'normal' !== $part && !self::isLength($part, false)) || 'overflow' === $property && array_any($parts, static fn (string $part) => !\in_array($part, ['visible', 'hidden', 'clip', 'scroll', 'auto'], true)))) {
                return [];
            }
            if ('overscroll-behavior' === $property && !$pending && !$wide && array_any($parts, static fn (string $part): bool => !\in_array(strtolower($part), ['auto', 'contain', 'none'], true))) {
                return [];
            }
            $values += array_combine($names, [$first, $second]);
        } elseif ('flex-flow' === $property) {
            $values += ['flex-direction' => 'row', 'flex-wrap' => 'nowrap'];
            foreach (ValueTokenizer::split($value) as $part) {
                if (\in_array($part, ['row', 'column', 'row-reverse', 'column-reverse'], true)) {
                    $values['flex-direction'] = $part;
                } elseif (\in_array($part, ['nowrap', 'wrap', 'wrap-reverse'], true)) {
                    $values['flex-wrap'] = $part;
                } else {
                    return [];
                }
            }
        } elseif (\in_array($property, ['grid-column', 'grid-row', 'grid-area', 'grid-template'], true)) {
            $parts = Selector::splitTopLevel($value, '/');
            $names = match ($property) {
                'grid-column' => ['grid-column-start', 'grid-column-end'],
                'grid-row' => ['grid-row-start', 'grid-row-end'],
                'grid-area' => ['grid-row-start', 'grid-column-start', 'grid-row-end', 'grid-column-end'],
                default => ['grid-template-rows', 'grid-template-columns'],
            };
            if (\count($parts) > \count($names)) {
                return [];
            }
            if ('grid-template' !== $property) {
                foreach ($names as $index => $name) {
                    if (!isset($parts[$index])) {
                        $start = $parts['grid-area' === $property && 3 === $index ? 1 : 0];
                        $parts[$index] = preg_match('/^(?!auto$|span$|default$)[a-z_][a-z0-9_-]*$/iD', $start) ? $start : 'auto';
                    }
                }
            }
            foreach ($names as $index => $name) {
                $values[$name] = $pending || $wide ? $value : ($parts[$index] ?? 'auto');
            }
        } elseif ('font' === $property) {
            $font = $pending || $wide ? array_fill_keys(['font-style', 'font-weight', 'font-variant', 'font-size', 'line-height', 'font-family'], $value) : self::font($value);
            if (null === $font) {
                return [];
            }
            $values += $font;
        } elseif ('list-style' === $property) {
            $list = ['list-style-type' => 'disc', 'list-style-position' => 'outside', 'list-style-image' => 'none'];
            foreach (ValueTokenizer::split($value) as $part) {
                if (\in_array(strtolower($part), ['inside', 'outside'], true)) {
                    $list['list-style-position'] = $part;
                } elseif (preg_match('/^(?:url|image-set|-webkit-image-set|(?:repeating-)?(?:linear|radial|conic)-gradient)\(/i', $part)) {
                    $list['list-style-image'] = $part;
                } else {
                    $list['list-style-type'] = $part;
                }
            }
            $values += $pending || $wide ? array_fill_keys(array_keys($list), $value) : $list;
        } elseif ('background' === $property) {
            $components = BackgroundParser::components($value);
            $values += $pending || $wide ? array_fill_keys(['background-image', 'background-position', 'background-size', 'background-repeat', 'background-origin', 'background-clip', 'background-attachment'], $value) : $components;
            $color = $pending || $wide ? $value : BackgroundColor::fromShorthand($value, static fn (string $color) => self::isColor($color, $colorValidator));
            if (false !== $color) {
                $values['background-color'] = $color;
            }
        } elseif (!$pending && !$wide && !self::validLonghand($property, $value, $colorValidator)) {
            return [];
        }

        return array_map(static fn (string $name, string $component) => new Declaration($name, $component, $declaration->important), array_keys($values), array_values($values));
    }

    public static function resolvedValue(string $property, string $source, string $value, ?\Closure $colorValidator = null): ?string
    {
        if ($property === $source) {
            return self::validLonghand($property, $value, $colorValidator) ? (str_starts_with($property, 'overscroll-behavior') ? strtolower($value) : $value) : null;
        }
        foreach (self::expand(new Declaration($source, $value), $colorValidator) as $expanded) {
            if ($expanded->property === $property) {
                return $expanded->value;
            }
        }

        return null;
    }

    public static function flex(string $value): ?array
    {
        $parts = ValueTokenizer::split($value);
        $keyword = strtolower($parts[0] ?? '');
        if (\in_array($keyword, ['none', 'auto', 'initial'], true)) {
            return 1 === \count($parts) ? ['auto' === $keyword ? '1' : '0', 'none' === $keyword ? '0' : '1', 'auto'] : null;
        }
        $numbers = array_values(array_filter($parts, 'is_numeric'));
        $rest = array_values(array_filter($parts, static fn (string $part) => !is_numeric($part)));
        if (3 === \count($numbers) && !$rest) {
            $rest = [array_pop($numbers)];
        }
        if (!$parts || \count($numbers) > 2 || \count($rest) > 1 || array_any($numbers, static fn (string $number) => (float) $number < 0) || isset($rest[0]) && !\in_array($rest[0], ['auto', 'content', 'min-content', 'max-content', 'fit-content'], true) && !self::isLength($rest[0], false)) {
            return null;
        }

        return [$numbers[0] ?? '1', $numbers[1] ?? '1', $rest[0] ?? '0'];
    }

    private static function validSides(string $property, array $parts, ?\Closure $colorValidator = null): bool
    {
        if ('border-radius' === $property) {
            foreach (Selector::splitTopLevel(implode(' ', $parts), '/') as $half) {
                $radii = ValueTokenizer::split($half);
                if (\count($radii) < 1 || \count($radii) > 4 || array_any($radii, static fn (string $part) => !self::isLength($part, false))) {
                    return false;
                }
            }

            return true;
        }
        foreach ($parts as $part) {
            if ('border-style' === $property ? !self::isBorderStyle($part) : ('border-color' === $property ? !self::isColor($part, $colorValidator) : !self::isLength($part, 'margin' === $property || 'inset' === $property, str_starts_with($property, 'border-')))) {
                return false;
            }
        }

        return true;
    }

    private static function validLonghand(string $property, string $value, ?\Closure $colorValidator = null): bool
    {
        if ('background-blend-mode' === $property) {
            return null !== \Symfony\Component\HtmlRenderer\Paint\BlendMode::fromList($value);
        }
        if ('white-space-collapse' === $property) {
            return \in_array(strtolower($value), ['collapse', 'preserve', 'preserve-breaks', 'preserve-spaces', 'break-spaces'], true);
        }
        if ('text-wrap-mode' === $property) {
            return \in_array(strtolower($value), ['wrap', 'nowrap'], true);
        }
        if ('text-transform' === $property) {
            return null !== \Symfony\Component\HtmlRenderer\Text\TextTransform::keywords($value);
        }
        if (\in_array($property, ['overscroll-behavior-x', 'overscroll-behavior-y', 'overscroll-behavior-inline', 'overscroll-behavior-block'], true)) {
            return \in_array(strtolower($value), ['auto', 'contain', 'none'], true);
        }
        if ('color-scheme' === $property) {
            return ColorScheme::valid($value);
        }
        if ('text-decoration-style' === $property) {
            return \in_array(strtolower($value), TextDecoration::STYLES, true);
        }
        if ('text-decoration-line' === $property) {
            return (bool) preg_match('/^(?:none|(?:underline|line-through|overline|blink)(?:\s+(?:underline|line-through|overline|blink))*)$/iD', $value);
        }
        if ('text-decoration-thickness' === $property) {
            return \in_array(strtolower($value), ['auto', 'from-font'], true) || TextDecoration::thickness($value) || (bool) preg_match('/^\d*\.?\d+%$/D', $value);
        }
        if ('scrollbar-color' === $property) {
            $parts = ValueTokenizer::split($value);

            return 'auto' === strtolower($value) || 2 === \count($parts) && array_all($parts, static fn (string $part) => self::isColor($part, $colorValidator));
        }
        if ('color' === $property || str_ends_with($property, '-color')) {
            return self::isColor($value, $colorValidator);
        }
        if (preg_match('/^(?:width|height|min-width|max-width|min-height|max-height|top|right|bottom|left|margin-(?:top|right|bottom|left)|padding-(?:top|right|bottom|left)|border-(?:top|right|bottom|left)-width|row-gap|column-gap)$/', $property)) {
            $keywords = match (true) {
                \in_array($property, ['width', 'height', 'min-width', 'min-height'], true) => ['auto', 'min-content', 'max-content', 'fit-content'],
                \in_array($property, ['max-width', 'max-height'], true) => ['none', 'min-content', 'max-content', 'fit-content'],
                \in_array($property, ['row-gap', 'column-gap'], true) => ['normal'],
                str_starts_with($property, 'margin-'), \in_array($property, ['top', 'right', 'bottom', 'left'], true) => ['auto'],
                default => [],
            };

            return \in_array($value, $keywords, true) || \in_array($property, ['height', 'min-height', 'max-height'], true) && null !== \Symfony\Component\HtmlRenderer\Animation\SizeInterpolation::calculation($value) || preg_match('/^(?:anchor(?:-size)?|fit-content)\([^()]+\)$/iD', $value) || self::isLength($value, str_starts_with($property, 'margin-') || \in_array($property, ['top', 'right', 'bottom', 'left'], true), str_starts_with($property, 'border-'));
        }

        return true;
    }

    private static function isLength(string $value, bool $negative, bool $border = false): bool
    {
        if ($negative && 'auto' === $value || $border && \in_array($value, ['thin', 'medium', 'thick'], true)) {
            return true;
        }
        $geometry = preg_replace('/\\banchor(?:-size)?\\([^()]*\\)/i', '0', $value);
        if ($geometry !== $value) {
            return self::isLength($geometry, $negative, $border);
        }
        $length = Length::evaluate($value, static fn (string $token): ?float => preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?(?:%|px|em|rem|ex|ch|lh|rlh|pt|pc|in|cm|mm|q|cq(?:w|h|i|b|min|max)|[sld]?v(?:w|h|i|b|min|max))?$/i', $token) ? (float) $token : null);

        return null !== $length && is_finite($length) && ($negative || Length::isMath($value) || $length >= 0);
    }

    /** @param-out array<string, true> $seen */
    private static function border(string $value, ?\Closure $colorValidator = null, ?array &$seen = null): ?array
    {
        $border = ['width' => 'medium', 'style' => 'none', 'color' => 'currentcolor'];
        $seen = [];
        foreach (ValueTokenizer::split($value) as $part) {
            $kind = self::isBorderStyle($part) ? 'style' : (self::isLength($part, false, true) && !isset($seen['width']) ? 'width' : (self::isColor($part, $colorValidator) ? 'color' : null));
            if (null === $kind || isset($seen[$kind])) {
                return null;
            }
            $border[$kind] = $part;
            $seen[$kind] = true;
        }

        return $seen ? $border : null;
    }

    /** @return array<string, true> */
    public static function implicitDefaults(string $property, string $value, ?\Closure $colorValidator = null): array
    {
        if (!preg_match('/^border(?:-(top|right|bottom|left))?$/D', $property, $match) || null === self::border($value, $colorValidator, $seen)) {
            return [];
        }
        $implicit = [];
        foreach (array_diff(['width', 'style', 'color'], array_keys($seen)) as $component) {
            foreach (isset($match[1]) ? [$match[1]] : ['top', 'right', 'bottom', 'left'] as $side) {
                $implicit['border-'.$side.'-'.$component] = true;
            }
            if (!isset($match[1])) {
                $implicit['border-'.$component] = true;
            }
        }

        return $implicit;
    }

    private static function isBorderStyle(string $value): bool
    {
        return \in_array(strtolower($value), ['none', 'hidden', 'solid', 'dotted', 'dashed', 'double', 'groove', 'ridge', 'inset', 'outset'], true);
    }

    private static function isColor(string $value, ?\Closure $colorValidator = null): bool
    {
        return 'currentcolor' === strtolower(trim($value)) || (null !== $colorValidator ? $colorValidator($value) : \is_array(ColorValue::parse($value)));
    }

    private static function font(string $value): ?array
    {
        $font = ['font-style' => 'normal', 'font-weight' => 'normal', 'font-variant' => 'normal', 'line-height' => 'normal'];
        $parts = ValueTokenizer::split($value);
        foreach ($parts as $index => $part) {
            $size = Selector::splitTopLevel($part, '/');
            if (self::isLength($size[0], false) && (!is_numeric($size[0]) || 0.0 === (float) $size[0])) {
                $font['font-size'] = $size[0];
                $rest = \array_slice($parts, $index + 1);
                if (isset($size[1])) {
                    $font['line-height'] = $size[1];
                } elseif ('/' === ($rest[0] ?? null)) {
                    array_shift($rest);
                    $font['line-height'] = array_shift($rest) ?? 'normal';
                }
                if (!$rest) {
                    return null;
                }
                $font['font-family'] = implode(' ', $rest);

                return $font;
            }
            if (\in_array($part, ['italic', 'oblique'], true)) {
                $font['font-style'] = $part;
            } elseif (\in_array($part, ['bold', 'bolder', 'lighter'], true) || is_numeric($part) && $part >= 1 && $part <= 1000) {
                $font['font-weight'] = $part;
            } elseif ('small-caps' === $part) {
                $font['font-variant'] = $part;
            } elseif ('normal' !== $part) {
                return null;
            }
        }

        return null;
    }

    public static function parentValue(array $parent, string $property): ?string
    {
        [$shorthand, $index] = self::SIDE_SHORTHANDS[$property] ?? [null, 0];
        if (null === $shorthand || !isset($parent[$shorthand])) {
            return $parent[$property] ?? null;
        }
        // The one declared last wins, like in the cascade
        $keys = array_flip(array_keys($parent));
        if (isset($parent[$property]) && $keys[$property] > $keys[$shorthand]) {
            return $parent[$property];
        }
        $value = $parent[$shorthand];
        if (preg_match('/^\s*(?:inherit|initial|unset|revert|revert-layer)\s*$/i', $value)) {
            return $value;
        }
        // One to four values, for the top, right, bottom and left, or the corners from the top left; for a radius, the
        // horizontal ones, then the vertical ones after a slash
        $halves = [];
        foreach ('border-radius' === $shorthand ? Selector::splitTopLevel($value, '/') : [$value] as $half) {
            $parts = ValueTokenizer::split(trim($half));
            $halves[] = match (\count($parts)) {
                0 => '',
                1 => $parts[0],
                2 => $parts[$index % 2],
                3 => $parts[[0, 1, 2, 1][$index]],
                default => $parts[$index],
            };
        }

        return implode(' / ', $halves);
    }

    public static function fontWeight(string $value): float
    {
        $value = strtolower(trim($value));

        return match (true) {
            'bold' === $value => 700,
            is_numeric($value) && $value >= 1 && $value <= 1000 => (float) $value,
            default => 400,
        };
    }

    public static function relativeFontWeight(string $relative, float $parent): float
    {
        if ('bolder' === $relative) {
            return match (true) {
                $parent < 350 => 400,
                $parent < 550 => 700,
                default => 900,
            };
        }

        return match (true) {
            $parent < 100 => $parent,
            $parent < 550 => 100,
            $parent < 750 => 400,
            default => 700,
        };
    }
}
