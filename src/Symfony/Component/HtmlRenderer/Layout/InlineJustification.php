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

use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\Paint\Transform;
use Symfony\Component\HtmlRenderer\Text\LineBreaks;
use Symfony\Component\HtmlRenderer\Text\TextRun;
use Symfony\Component\HtmlRenderer\Text\TextSelection;

/** @internal */
final class InlineJustification
{
    public static function apply(array $fragments, float $free, \Closure $measure, \Closure $translate, \Closure $distribute, int &$remaining, string $mode = 'auto'): array
    {
        if ($free <= 0) {
            return $fragments;
        }
        $text = 'auto' === $mode ? implode('', array_map(self::text(...), $fragments)) : '';
        $interCharacter = 'inter-character' === $mode || 'auto' === $mode && !str_contains($text, ' ') && LineBreaks::isIdeographic($text);
        $pieces = new \SplObjectStorage();
        $atomic = new \SplObjectStorage();
        $units = [];
        foreach ($fragments as $fragment) {
            self::collect($fragment, $pieces, $atomic, $units, $measure, $remaining, $interCharacter);
        }
        usort($units, static fn (array $first, array $second): int => $first['x'] <=> $second['x']);
        $seen = false;
        $pending = $opportunities = [];
        foreach ($units as $unit) {
            if ($unit['space']) {
                if ($seen && $unit['width'] > 0) {
                    $pending[] = $unit;
                }
            } else {
                if ($seen) {
                    array_push($opportunities, ...$pending);
                }
                $pending = [];
                $seen = true;
            }
        }
        if ($interCharacter) {
            $opportunities = \array_slice($units, 0, -1);
        }
        if (!$opportunities) {
            return $fragments;
        }
        $extra = $distribute(array_fill(0, \count($opportunities), $free / \count($opportunities)));
        $gaps = $expansions = [];
        $total = 0.0;
        foreach ($opportunities as $index => $opportunity) {
            $total += $extra[$index];
            $gaps[] = [$opportunity['x'] + $opportunity['width'], $total];
            $expansions[$opportunity['id']] = $extra[$index];
        }
        $shift = static function (float $x) use ($gaps): float {
            $low = 0;
            $high = \count($gaps);
            while ($low < $high) {
                $middle = intdiv($low + $high, 2);
                $gaps[$middle][0] <= $x + 4 * \PHP_FLOAT_EPSILON * max(1.0, abs($x)) ? $low = $middle + 1 : $high = $middle;
            }

            return 0 === $low ? 0.0 : $gaps[$low - 1][1];
        };
        $replace = static function (Fragment $fragment) use (&$replace, $pieces, $atomic, $expansions, $shift, $translate, &$remaining): Fragment {
            $dx = $shift($fragment->bounds->x);
            if (isset($atomic[$fragment])) {
                return 0.0 === $dx ? $fragment : $translate($fragment, $dx, 0.0);
            }
            $growth = $shift($fragment->bounds->x + $fragment->bounds->width) - $dx;
            if (isset($pieces[$fragment])) {
                $parts = $pieces[$fragment];
                if (!$parts || 1 === \count($parts) && !isset($expansions[$parts[0]['id']])) {
                    return 0.0 === $dx ? $fragment : $translate($fragment, $dx, 0.0);
                }
                $children = [];
                foreach ($parts as $partIndex => $part) {
                    $extra = $expansions[$part['id']] ?? 0.0;
                    $width = $part['width'] + $extra;
                    $run = $fragment->run?->slice($part['start'], $part['length']);
                    if (null !== $run && $extra > 0) {
                        $positions = [];
                        for ($index = 0; $index <= $part['length']; ++$index) {
                            $positions[] = $width * ($index / $part['length']);
                        }
                        if ($part['reversed']) {
                            $positions = array_map(static fn (float $position): float => $width - $position, $positions);
                        }
                        $run = new TextRun($run->start, $run->end, $run->selection, $run->sourceOffsets, $positions);
                    }
                    if (null !== $run?->selection && $run->selection->caretVisible && $run->selection->focus === $run->end && isset($parts[$partIndex + 1])) {
                        $selection = new TextSelection($run->selection->anchor, $run->selection->focus, false, $run->selection->foreground, $run->selection->background);
                        $run = new TextRun($run->start, $run->end, $selection, $run->sourceOffsets, $run->caretPositions);
                    }
                    if (--$remaining < 0) {
                        throw new \LengthException('Inline justification exceeds the fragment count limit.');
                    }
                    $children[] = new Fragment($fragment->node, new Rect($part['x'] + $shift($part['x']), $fragment->bounds->y, $width, $fragment->bounds->height), $fragment->style, $part['text'], [], null, null, [], $run, $fragment->pseudoElement);
                }
            } else {
                $children = array_map($replace, $fragment->children);
                if (0.0 === $dx && 0.0 === $growth && $children === $fragment->children) {
                    return $fragment;
                }
            }
            if (--$remaining < 0) {
                throw new \LengthException('Inline justification exceeds the fragment count limit.');
            }
            $bounds = new Rect($fragment->bounds->x + $dx, $fragment->bounds->y, $fragment->bounds->width + $growth, $fragment->bounds->height);

            return new Fragment($fragment->node, $bounds, $fragment->style, null, $children, $fragment->image, null === $fragment->transform ? null : Transform::moved($fragment->transform, $dx, 0), $fragment->backgrounds, null, $fragment->pseudoElement, $fragment->topLayer, $fragment->scroll?->moved($dx, 0), $fragment->borderImage, $fragment->anchor, $fragment->content);
        };

        return array_map($replace, $fragments);
    }

    private static function text(Fragment $fragment): string
    {
        if (null !== $fragment->image || null !== $fragment->content) {
            return ' ';
        }

        return $fragment->text ?? implode('', array_map(self::text(...), $fragment->children));
    }

    private static function collect(Fragment $fragment, \SplObjectStorage $pieces, \SplObjectStorage $atomic, array &$units, \Closure $measure, int $remaining, bool $interCharacter): void
    {
        if (null !== $fragment->image || null !== $fragment->content || \in_array($fragment->style['display'] ?? 'inline', ['inline-block', 'inline-flex', 'inline-grid', 'inline-table', 'ruby', 'ruby-pair'], true)) {
            if (\count($units) >= $remaining) {
                throw new \LengthException('Inline justification exceeds the fragment count limit.');
            }
            $atomic[$fragment] = true;
            $units[] = ['id' => \count($units), 'x' => $fragment->bounds->x, 'width' => $fragment->bounds->width, 'space' => false];

            return;
        }
        if (null !== $fragment->text) {
            $parts = [];
            $start = 0;
            $cursor = 0.0;
            $count = preg_match_all('/\X/u', $fragment->text) ?: 0;
            $positions = $fragment->run->caretPositions ?? $measure($fragment->text, $fragment)->caretPositions;
            $texts = $interCharacter ? (preg_match_all('/\X/u', $fragment->text, $matches) ? $matches[0] : []) : (preg_split('/( +)/u', $fragment->text, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) ?: []);
            foreach ($texts as $text) {
                if (\count($units) >= $remaining) {
                    throw new \LengthException('Inline justification exceeds the fragment count limit.');
                }
                $length = preg_match_all('/\X/u', $text) ?: 0;
                $reversed = false;
                if (null !== $positions && \count($positions) === $count + 1) {
                    $partPositions = \array_slice($positions, $start, $length + 1);
                    $left = min($partPositions);
                    $width = max($partPositions) - $left;
                    $reversed = $partPositions[0] > $partPositions[$length];
                } else {
                    $left = $cursor;
                    $width = $measure($text, $fragment)->width;
                    $cursor += $width;
                }
                $part = ['id' => \count($units), 'text' => $text, 'start' => $start, 'length' => $length, 'x' => $fragment->bounds->x + $left, 'width' => $width, 'space' => '' === trim($text, ' '), 'reversed' => $reversed];
                $units[] = $part;
                $parts[] = $part;
                $start += $length;
            }
            $pieces[$fragment] = $parts;

            return;
        }
        foreach ($fragment->children as $child) {
            self::collect($child, $pieces, $atomic, $units, $measure, $remaining, $interCharacter);
        }
    }
}
