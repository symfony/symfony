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

/**
 * Resolves raw declarations from an explicitly selected set of rules, without evaluating media or element state.
 *
 * @internal
 */
final class SpecifiedCascade
{
    /** @param list<CssRule> $rules Rules selected by the caller's query policy */
    public static function resolve(array $rules, array $inline = [], array $hints = [], array $inherited = [], array $ignored = []): array
    {
        $ignored = array_flip($ignored);
        $entries = [];
        foreach ($rules as $rule) {
            foreach ($rule->declarations as $position => $declaration) {
                if (!isset($ignored[$declaration->property])) {
                    $entries[] = [CascadePriority::of($declaration, $rule->selector->specificity, $rule->order, $rule->layer, $rule->userAgent, $position, $rule->scoped, $rule->context), $declaration, !$rule->userAgent];
                }
            }
        }
        foreach ($inline as $position => $declaration) {
            if (!isset($ignored[$declaration->property])) {
                $entries[] = [CascadePriority::of($declaration, \PHP_INT_MAX, \PHP_INT_MAX, CssRule::UNLAYERED, false, $position), $declaration, true];
            }
        }
        foreach ($hints as $position => $declaration) {
            if (!isset($ignored[$declaration->property])) {
                $entries[] = [CascadePriority::of($declaration, 0, -1, CssRule::UNLAYERED, false, $position), $declaration, true];
            }
        }
        usort($entries, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        // What the user agent alone gives, which "revert" goes back to in an author rule
        $userAgent = [];
        foreach ($entries as [$rank, $declaration, $author]) {
            if (!$author && !$declaration->important && 'all' !== $declaration->property) {
                $userAgent[$declaration->property] = $declaration->value;
            }
        }
        $declarations = [];
        foreach ($entries as [$rank, $declaration, $author]) {
            $property = $declaration->property;
            $keyword = strtolower(trim($declaration->value));
            // all: a CSS-wide keyword for every property but the custom ones, direction and unicode-bidi
            if ('all' === $property) {
                if (!\in_array($keyword, ['initial', 'inherit', 'unset', 'revert', 'revert-layer'], true)) {
                    continue;
                }
                // A user agent can give appearance a different initial native look.
                $affected = array_keys($declarations + array_flip($inherited) + $userAgent + ['appearance' => true]);
                foreach ($affected as $name) {
                    if (CssWideKeywords::affected([$name]) && !isset($ignored[$name])) {
                        unset($declarations[$name]);
                        $declarations[$name] = CssWideKeywords::resolve($keyword, $name, $author, $userAgent, $inherited);
                    }
                }
                continue;
            }
            // font sets its size and its families too, a length or a keyword before the line height and what follows
            if ('font' === $property) {
                unset($declarations['font']);
                $declarations['font'] = $declaration->value;
                if (preg_match('#(?:^|\s)((?:[+-]?(?:\d+\.?\d*|\.\d+)(?:[a-z]+|%)|(?:xx?-|xxx-)?(?:small|large)|medium|larger|smaller))(?:\s*/\s*[^\s,]+)?\s+(\S.*)$#i', trim($declaration->value), $m, \PREG_OFFSET_CAPTURE)) {
                    // Its style and its weight before the size, normal unless set, which the children inherit: not a
                    // bold declared before it, like a font: 12px sans-serif after a font-weight: bold
                    $style = $weight = 'normal';
                    foreach (preg_split('/\s+/', strtolower(substr(trim($declaration->value), 0, $m[1][1]))) ?: [] as $token) {
                        if (\in_array($token, ['italic', 'oblique'], true)) {
                            $style = $token;
                        } elseif (\in_array($token, ['bold', 'bolder', 'lighter'], true) || preg_match('/^\d+$/', $token)) {
                            $weight = $token;
                        }
                    }
                    $variant = preg_match('/(?<![\w-])small-caps(?![\w-])/i', $declaration->value) ? 'small-caps' : 'normal';
                    $lineHeight = preg_match('#^\s*/\s*([^\s,]+)#', substr(trim($declaration->value), $m[1][1] + \strlen($m[1][0])), $l) ? $l[1] : 'normal';
                    foreach (['font-size' => $m[1][0], 'font-family' => $m[2][0], 'font-style' => $style, 'font-weight' => $weight, 'font-variant' => $variant, 'font-variant-caps' => $variant, 'line-height' => $lineHeight] as $name => $value) {
                        if (isset($ignored[$name])) {
                            continue;
                        }
                        unset($declarations[$name]);
                        $declarations[$name] = $value;
                    }
                }
                continue;
            }
            if (isset($ignored['background-color']) && 'background' === $property) {
                $declarations[$property] = $declaration->value;
                continue;
            }
            // text-emphasis sets its longhands, which the children inherit, and which its own longhands replace
            if ('text-emphasis' === $property) {
                foreach (TextEmphasis::parse($declaration->value) as $name => $value) {
                    unset($declarations[$name]);
                    $declarations[$name] = \in_array(strtolower(trim($value)), ['unset', 'revert', 'revert-layer'], true) ? CssWideKeywords::resolve(strtolower(trim($value)), $name, $author, $userAgent, $inherited) : $value;
                }
                continue;
            }
            // Later wins: keep the insertion order of the winner
            unset($declarations[$property]);
            $declarations[$property] = \in_array($keyword, ['unset', 'revert', 'revert-layer'], true) && !str_starts_with($property, '--') ? CssWideKeywords::resolve($keyword, $property, $author, $userAgent, $inherited) : $declaration->value;
        }

        return $declarations;
    }
}
