<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Layout;

use Symfony\Component\HtmlRenderer\Text\LineBreaks;

/** @internal */
final class InlineFormatting
{
    /**
     * Breaks undecorated inline descendants at the same text opportunities used
     * by line layout. Repeated ancestor boxes retain their document identity.
     *
     * @return list<Box>|null
     */
    public static function split(Box $box, bool $emergency = false): ?array
    {
        if (!\in_array($box->display, ['inline', 'ruby'], true) || null !== $box->replacedContent || null !== $box->image || null !== $box->selection || null !== $box->topLayer || 'static' !== ($box->style['position'] ?? 'static') || 'none' !== ($box->style['transform'] ?? 'none') || !\in_array($box->style['white-space'] ?? 'normal', ['normal', 'pre-line'], true)) {
            return null;
        }
        if (!self::undecorated($box)) {
            return null;
        }
        if ($emergency && 'anywhere' === ($box->style['overflow-wrap'] ?? '')) {
            $style = $box->style;
            $style['-renderer-emergency-break'] = '1';
        } else {
            $style = $box->style;
        }
        $parts = [];
        $text = $box->text ?? ($box->node instanceof \Dom\Text ? $box->node->textContent : null);
        if (null !== $text) {
            $text = (string) preg_replace('/[\t\r\n\f ]+/', ' ', $text);
            $tokens = LineBreaks::tokens($text, $box->style['word-break'] ?? 'normal');
            if (isset($style['-renderer-emergency-break'])) {
                preg_match_all('/\\X/u', $text, $graphemes);
                $tokens = $graphemes[0];
            }
            foreach ($tokens as $token) {
                $parts[] = new Box($box->node, $box->display, $style, [], $token, null, null, $box->pseudoElement);
            }
        }
        foreach ($box->children as $child) {
            $atomic = (null !== $child->replacedContent || \in_array($child->display, ['inline-block', 'inline-flex', 'inline-grid', 'inline-table', 'ruby-pair'], true)) && 'static' === ($child->style['position'] ?? 'static') && 'none' === ($child->style['float'] ?? 'none') && null === $child->topLayer;
            $children = $atomic ? [$child] : self::split($child, $emergency);
            if (null === $children) {
                return null;
            }
            foreach ($children as $part) {
                $parts[] = new Box($box->node, $box->display, $style, [$part], null, null, null, $box->pseudoElement);
            }
        }

        return $parts ?: [$box];
    }

    /**
     * Lifts in-flow block descendants out of an undecorated inline's visual box.
     * The semantic ancestry and inherited styles remain those of the native DOM.
     *
     * @return list<Box>|null
     */
    public static function splitBlocks(Box $box, int &$remaining): ?array
    {
        if ('inline' !== $box->display || null !== $box->text || null !== $box->image || null !== $box->replacedContent || null !== $box->selection || null !== $box->topLayer || 'static' !== ($box->style['position'] ?? 'static') || 'none' !== ($box->style['float'] ?? 'none') || 'none' !== ($box->style['transform'] ?? 'none') || !self::undecorated($box)) {
            return null;
        }
        $parts = $inline = [];
        $split = false;
        $flush = static function (array $inline) use ($box, &$remaining): ?Box {
            if (!$inline) {
                return null;
            }
            if (--$remaining < 0) {
                throw new \LengthException('Inline fragmentation exceeds the box count limit.');
            }

            return new Box($box->node, $box->display, $box->style, $inline, null, null, null, $box->pseudoElement, null, $box->borderImage, $box->anchor);
        };
        foreach ($box->children as $child) {
            foreach (self::splitBlocks($child, $remaining) ?? [$child] as $part) {
                if (!\in_array($part->display, ['inline', 'inline-block', 'inline-flex', 'inline-grid', 'inline-table', 'ruby', 'ruby-pair', 'line-break', 'none'], true) && 'none' === ($part->style['float'] ?? 'none') && !\in_array($part->style['position'] ?? 'static', ['absolute', 'fixed'], true) && null === $part->topLayer) {
                    if (null !== $flushed = $flush($inline)) {
                        $parts[] = $flushed;
                    }
                    $inline = [];
                    $parts[] = $part;
                    $split = true;
                } else {
                    $inline[] = $part;
                }
            }
        }
        if (!$split) {
            return null;
        }
        if (null !== $flushed = $flush($inline)) {
            $parts[] = $flushed;
        }

        return $parts;
    }

    private static function undecorated(Box $box): bool
    {
        foreach ($box->style as $property => $value) {
            if (\in_array($property, ['margin-top', 'margin-bottom', 'margin-block', 'margin-block-start', 'margin-block-end'], true)) {
                continue;
            }
            if (preg_match('/^(?:margin|padding|border)(?:-|$)/', $property) && !\in_array($value, ['none', 'hidden', 'currentcolor', 'currentColor'], true) && !preg_match('/^0(?:\.0+)?(?:px|em|rem|ch|lh|%)?(?:\s+0(?:\.0+)?(?:px|em|rem|ch|lh|%)?)*$/', $value) || str_starts_with($property, 'background') && '1' !== ($box->style['-renderer-highlight'] ?? null) && !\in_array($value, ['none', 'transparent'], true) || \in_array($property, ['opacity', 'filter', 'isolation'], true) && !\in_array($value, ['1', 'none', 'auto'], true)) {
                return false;
            }
        }

        return true;
    }

    public static function joins(Box $previous, Box $next): bool
    {
        $left = self::edge($previous, true);
        $right = self::edge($next, false);
        if (null === $left || null === $right || '' === $left[0] || '' === $right[0]) {
            return false;
        }
        foreach ([$left[1], $right[1]] as $style) {
            if (isset($style['-renderer-emergency-break']) || 'break-all' === ($style['word-break'] ?? '')) {
                return false;
            }
        }

        return 1 === \count(LineBreaks::tokens($left[0].$right[0], $right[1]['word-break'] ?? $left[1]['word-break'] ?? 'normal'));
    }

    private static function edge(Box $box, bool $end): ?array
    {
        if ('inline' !== $box->display || null !== $box->replacedContent || null !== $box->image || 'static' !== ($box->style['position'] ?? 'static')) {
            return null;
        }
        $text = $box->text ?? ($box->node instanceof \Dom\Text ? $box->node->textContent : null);
        if (null !== $text && '' !== $text) {
            preg_match_all('/\\X/u', $text, $matches);

            return [$end ? end($matches[0]) : $matches[0][0], $box->style];
        }
        $children = $end ? array_reverse($box->children) : $box->children;
        foreach ($children as $child) {
            if ('inline' !== $child->display || null !== $child->image) {
                return null;
            }
            if (null !== $edge = self::edge($child, $end)) {
                return $edge;
            }
        }

        return null;
    }

    /** @return list<Box> */
    public static function collapseWhitespace(array $boxes, \Closure $copy, string $whiteSpace = 'normal'): array
    {
        $space = false;
        $visit = static function (Box $box, string $whiteSpace) use (&$visit, &$space, $copy): Box {
            if ('none' === $box->display || null !== $box->topLayer || \in_array($box->style['position'] ?? 'static', ['absolute', 'fixed'], true) || 'none' !== ($box->style['float'] ?? 'none')) {
                return $box;
            }
            $whiteSpace = $box->style['white-space'] ?? $whiteSpace;
            if ('inline' !== $box->display || null !== $box->image || null !== $box->replacedContent || !\in_array($whiteSpace, ['normal', 'nowrap', 'pre-line'], true)) {
                $space = false;

                return $box;
            }
            $style = $box->style;
            $text = $box->text ?? ($box->node instanceof \Dom\Text ? $box->node->textContent : null);
            if (null !== $text && '' !== $text) {
                $characters = 'pre-line' === $whiteSpace ? " \t\r\f" : " \t\r\n\f";
                if ($space && strspn($text, $characters) > 0) {
                    $style['-renderer-collapse-leading-space'] = '1';
                }
                $space = strspn(substr($text, -1), $characters) > 0;
            }
            $children = array_map(static fn (Box $child): Box => $visit($child, $whiteSpace), $box->children);
            if ($style === $box->style && $children === $box->children) {
                return $box;
            }

            return $copy($box, new Box($box->node, $box->display, $style, $children, $box->text, $box->image, $box->selection, $box->pseudoElement, $box->topLayer, $box->borderImage, $box->anchor, $box->replacedContent, $box->anchorReferences, $box->positionTries));
        };

        return array_map(static fn (Box $box): Box => $visit($box, $whiteSpace), $boxes);
    }

    public static function trimBoundary(Box $box, bool $end): Box
    {
        $text = $box->text ?? ($box->node instanceof \Dom\Text ? $box->node->textContent : null);
        $children = $box->children;
        if (null !== $text) {
            $text = $end ? rtrim($text, ' ') : ltrim($text, ' ');
        } elseif ($children) {
            $index = $end ? array_key_last($children) : array_key_first($children);
            $children[$index] = self::trimBoundary($children[$index], $end);
        }

        return new Box($box->node, $box->display, $box->style, $children, $text, $box->image, $box->selection, $box->pseudoElement, $box->topLayer, $box->borderImage, $box->anchor, $box->replacedContent, $box->anchorReferences, $box->positionTries);
    }
}
