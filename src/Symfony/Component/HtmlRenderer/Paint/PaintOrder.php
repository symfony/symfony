<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Paint;

use Symfony\Component\HtmlRenderer\Layout\Fragment;
use Symfony\Component\HtmlRenderer\Vector\Geometry;

/**
 * @internal
 */
final class PaintOrder
{
    /**
     * @return list<array{type: string, fragment: Fragment, clips: array, matrix: array{float, float, float, float, float, float}, vertical-align: ?string, baseline-shift: float}>
     */
    public static function build(Fragment $root): array
    {
        $matrices = new \SplObjectStorage();
        $topLayers = [];
        $alignments = new \SplObjectStorage();
        $visit = static function (Fragment $fragment, array $parent, ?string $script = null, float $shift = 0.0) use (&$visit, $matrices, $alignments, &$topLayers): void {
            if (null !== $fragment->topLayer) {
                $parent = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
                $topLayers[] = $fragment;
                $script = null;
                $shift = 0.0;
            }
            $matrix = null === $fragment->transform ? $parent : Geometry::multiply($parent, $fragment->transform);
            $matrices[$fragment] = $matrix;
            $alignment = $fragment->style['vertical-align'] ?? 'baseline';
            if (\in_array($alignment, ['super', 'sub', 'top', 'bottom', 'middle', 'text-top', 'text-bottom'], true) && (\in_array($alignment, ['super', 'sub'], true) || !\in_array($script, ['super', 'sub'], true))) {
                $script = $alignment;
            }
            $shift += (float) ($fragment->style['-renderer-baseline-shift'] ?? 0);
            $alignments[$fragment] = [$script, $shift];
            foreach ($fragment->children as $child) {
                $visit($child, $matrix, $script, $shift);
            }
        };
        $visit($root, [1.0, 0.0, 0.0, 1.0, 0.0, 0.0]);
        $steps = null === $root->topLayer ? self::context($root, [], [], []) : [];
        usort($topLayers, static fn (Fragment $first, Fragment $second): int => [$first->topLayer, 'backdrop' === $first->pseudoElement ? 0 : 1] <=> [$second->topLayer, 'backdrop' === $second->pseudoElement ? 0 : 1]);
        foreach ($topLayers as $layer) {
            array_push($steps, ...self::context($layer, [], [], []));
        }
        foreach ($steps as &$step) {
            $step['matrix'] = $matrices[$step['fragment']];
            [$step['vertical-align'], $step['baseline-shift']] = $alignments[$step['fragment']];
            foreach ($step['clips'] as &$clip) {
                $clip['matrix'] = $matrices[$clip['owner']];
            }
            unset($clip);
        }
        unset($step);

        return $steps;
    }

    private static function context(Fragment $root, array $clips, array $path, array $levels): array
    {
        $inside = self::clips($root, $clips);
        $flow = ['block' => [], 'float' => [], 'inline' => []];
        $outlines = [];
        if (self::hasContent($root)) {
            $flow['inline'][] = self::step('content', $root, $inside);
        }
        $layers = [];
        foreach ($root->children as $index => $child) {
            self::collect($child, 'table-border' === $child->pseudoElement ? $clips : $inside, [...$path, $index], $levels, $root->style['display'] ?? 'block', $flow, $layers, $outlines);
        }
        usort($layers, static fn (array $first, array $second): int => StackingOrder::compare($first['key'], $second['key']));
        $out = [self::step('context-enter', $root, $clips), self::step('background', $root, $clips)];
        foreach ($layers as $layer) {
            if ($layer['z'] < 0) {
                array_push($out, ...self::layer($layer));
            }
        }
        foreach ($flow as $steps) {
            array_push($out, ...$steps);
        }
        // CSS 2.2 Appendix E permits outlines here, before positioned layers (steps 7–9).
        // https://www.w3.org/TR/CSS22/zindex.html
        // This early policy also preserves the terminal renderer's outline-before-overlay order.
        array_push($out, ...$outlines);
        if (self::hasOutline($root)) {
            $out[] = self::step('outline', $root, $clips);
        }
        foreach ($layers as $layer) {
            if ($layer['z'] >= 0) {
                array_push($out, ...self::layer($layer));
            }
        }
        if ($root->scroll?->horizontalBar || $root->scroll?->verticalBar) {
            $out[] = self::step('scrollbar', $root, $clips);
        }
        $out[] = self::step('context-exit', $root, $clips);

        return $out;
    }

    private static function collect(Fragment $fragment, array $clips, array $path, array $levels, string $parentDisplay, array &$flow, array &$layers, array &$outlines): void
    {
        if (null !== $fragment->topLayer) {
            return;
        }
        $style = $fragment->style;
        $positioned = 'static' !== ($style['position'] ?? 'static');
        $flexItem = \in_array($parentDisplay, ['flex', 'inline-flex', 'grid', 'inline-grid'], true);
        $hasZ = isset($style['z-index']) && 'auto' !== $style['z-index'];
        $z = ($positioned || $flexItem) && $hasZ ? (int) $style['z-index'] : 0;
        $context = null !== $fragment->transform || $positioned && $hasZ || $flexItem && $hasZ || \in_array($style['position'] ?? 'static', ['fixed', 'sticky'], true)
            || (float) ($style['opacity'] ?? 1) < 1 || 'isolate' === ($style['isolation'] ?? 'auto')
            || 'none' !== ($style['transform'] ?? 'none') || 'none' !== ($style['filter'] ?? 'none')
            || 'none' !== ($style['backdrop-filter'] ?? 'none') || 'none' !== ($style['perspective'] ?? 'none')
            || 'normal' !== ($style['mix-blend-mode'] ?? 'normal')
            || (bool) preg_match('/(?:^|\s)(?:paint|layout|strict|content)(?:\s|$)/', $style['contain'] ?? '')
            || (bool) preg_match('/(?:^|[\s,])(?:transform|opacity|filter|perspective)(?:[\s,]|$)/', $style['will-change'] ?? '');
        if ($context) {
            $ownLevels = [...$levels, [$z, \count($path)]];
            $layers[] = ['fragment' => $fragment, 'clips' => $clips, 'path' => $path, 'levels' => $ownLevels, 'key' => ['path' => $path, 'levels' => $ownLevels], 'z' => $z];

            return;
        }
        if ($positioned) {
            $steps = self::chunk($fragment, $clips, $path, $levels, $layers);
            $layers[] = ['steps' => $steps, 'key' => ['path' => $path, 'levels' => [...$levels, [0, \count($path)]]], 'z' => 0];

            return;
        }
        $display = $style['display'] ?? 'block';
        if (\in_array($style['float'] ?? 'none', ['left', 'right'], true)) {
            array_push($flow['float'], ...self::chunk($fragment, $clips, $path, $levels, $layers));

            return;
        }
        if (\in_array($display, ['inline-block', 'inline-table', 'inline-flex', 'inline-grid'], true)) {
            array_push($flow['inline'], ...self::chunk($fragment, $clips, $path, $levels, $layers));

            return;
        }
        $flow['inline' === $display ? 'inline' : 'block'][] = self::step('background', $fragment, $clips);
        $inside = self::clips($fragment, $clips);
        if (self::hasContent($fragment)) {
            $flow['inline'][] = self::step('content', $fragment, $inside);
        }
        foreach ($fragment->children as $index => $child) {
            self::collect($child, 'table-border' === $child->pseudoElement ? $clips : $inside, [...$path, $index], $levels, $display, $flow, $layers, $outlines);
        }
        if ($fragment->scroll?->horizontalBar || $fragment->scroll?->verticalBar) {
            $flow['inline'][] = self::step('scrollbar', $fragment, $clips);
        }
        if (self::hasOutline($fragment)) {
            $outlines[] = self::step('outline', $fragment, $clips);
        }
    }

    private static function hasOutline(Fragment $fragment): bool
    {
        return !\in_array($fragment->style['outline-style'] ?? 'none', ['none', 'hidden'], true)
            && null !== $fragment->outline && max($fragment->outline->widthX, $fragment->outline->widthY) > 0;
    }

    private static function hasContent(Fragment $fragment): bool
    {
        return null !== $fragment->content || null !== $fragment->text || null !== $fragment->image || (null === $fragment->pseudoElement && $fragment->node instanceof \Dom\Element && 'iframe' === strtolower($fragment->node->localName));
    }

    private static function chunk(Fragment $fragment, array $clips, array $path, array $levels, array &$layers): array
    {
        $inside = self::clips($fragment, $clips);
        $flow = ['block' => [], 'float' => [], 'inline' => []];
        $outlines = [];
        if (self::hasContent($fragment)) {
            $flow['inline'][] = self::step('content', $fragment, $inside);
        }
        foreach ($fragment->children as $index => $child) {
            self::collect($child, 'table-border' === $child->pseudoElement ? $clips : $inside, [...$path, $index], $levels, $fragment->style['display'] ?? 'block', $flow, $layers, $outlines);
        }
        $out = [self::step('background', $fragment, $clips)];
        foreach ($flow as $steps) {
            array_push($out, ...$steps);
        }

        array_push($out, ...$outlines);
        if (self::hasOutline($fragment)) {
            $out[] = self::step('outline', $fragment, $clips);
        }
        if ($fragment->scroll?->horizontalBar || $fragment->scroll?->verticalBar) {
            $out[] = self::step('scrollbar', $fragment, $clips);
        }

        return $out;
    }

    private static function layer(array $layer): array
    {
        return $layer['steps'] ?? self::context($layer['fragment'], $layer['clips'], $layer['path'], $layer['levels']);
    }

    private static function clips(Fragment $fragment, array $ancestors): array
    {
        $style = $fragment->style;
        $x = \in_array($style['overflow-x'] ?? $style['overflow'] ?? 'visible', ['hidden', 'clip', 'scroll', 'auto'], true);
        $y = \in_array($style['overflow-y'] ?? $style['overflow'] ?? 'visible', ['hidden', 'clip', 'scroll', 'auto'], true);
        if ($x || $y) {
            $ancestors[] = ['owner' => $fragment, 'rect' => $fragment->scroll->port ?? $fragment->getPaddingBox(), 'clip-x' => $x, 'clip-y' => $y];
        }

        return $ancestors;
    }

    private static function step(string $type, Fragment $fragment, array $clips): array
    {
        return ['type' => $type, 'fragment' => $fragment, 'clips' => $clips];
    }
}
