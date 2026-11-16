<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Render;

use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ParentInterface;
use Symfony\Component\TuiHtml\Style\Position;
use Symfony\Component\TuiHtml\Style\PositionMode;
use Symfony\Component\TuiHtml\Style\Style;

/**
 * The order the widgets drawn over the flow are drawn in, like the stacking contexts of CSS.
 *
 * A widget is ordered among the others of its closest ancestor that is a stacking context, by z-index then in document
 * order, and that context among the others of its own: a widget of z-index 100 inside one of z-index 1 is under a
 * sibling of that one of z-index 2. A context is drawn over those of a negative z-index inside it and under the others.
 *
 * The flow is one layer: a context in it, like an element of opacity below 1, is drawn there, a z-index of 0, whatever
 * its z-index. A modal dialog or an open popover is in the top layer, over the others whatever the contexts around it. A negative z-index directly in the page is drawn under the flow; in a context drawn over it, under the
 * content of that context and over its background.
 *
 * @experimental
 *
 * @internal
 */
final class StackingOrder
{
    /**
     * Whether a widget is a stacking context: a positioned one of a z-index other than 0, or one isolated, like
     * isolation: isolate, opacity below 1, a filter, a transform or z-index: 0.
     */
    public static function isContext(Style $style): bool
    {
        return true === $style->getIsolated() || 0 !== ($style->getPosition()->zIndex ?? 0);
    }

    /**
     * Whether a widget is drawn over the flow, out of its lines: absolute, fixed, or relative of a nonzero z-index.
     */
    public static function isRaised(?Position $position): bool
    {
        return null !== $position && ($position->isOutOfFlow() || PositionMode::Relative === $position->mode && 0 !== $position->zIndex);
    }

    /**
     * The key ordering a widget drawn over the flow, null when it is not inside the root.
     *
     * The key holds the path of the widget from the root, its document order, and its levels: for each stacking context
     * around it, then for itself, a z-index and the depth of that widget on the path; a context ends with a level of
     * z-index 0 at its own depth, under the levels of what is inside it of z-index 0 and more. Its place tells where it
     * is drawn: over the flow (false), under it (true), or under the content of a context drawn over the flow.
     *
     * @param \Closure(AbstractWidget): Style    $style
     * @param array<int, array<int, int|string>> $indexes The places of the children of the parents met, shared by the keys of one sort
     *
     * @return array{path: list<int>, levels: list<array{int, int}>, under: bool|AbstractWidget, context?: AbstractWidget|null}|null
     */
    public static function key(AbstractWidget $widget, AbstractWidget $root, \Closure $style, array &$indexes = []): ?array
    {
        $chain = [];
        $path = [];
        for ($current = $widget; $current !== $root; $current = $parent) {
            if (null === $parent = $current->getParent()) {
                return null;
            }
            $chain[] = $current;
            // The place of each child of a parent, found once for all the keys made with the same $indexes
            $parentId = spl_object_id($parent);
            if (!isset($indexes[$parentId])) {
                $indexes[$parentId] = [];
                foreach ($parent instanceof ParentInterface ? $parent->all() : [] as $index => $child) {
                    $indexes[$parentId][spl_object_id($child)] = $index;
                }
            }
            array_unshift($path, (int) ($indexes[$parentId][spl_object_id($current)] ?? 0));
        }
        $chain = array_reverse($chain);
        $levels = [];
        $owners = [];
        $contexts = [];
        foreach ($chain as $depth => $current) {
            $currentStyle = $style($current);
            // A modal dialog or an open popover is in the top layer, over the page, whatever the contexts around it
            $top = $current->hasState('modal') || $current->hasState('popover-open');
            $context = $top || self::isContext($currentStyle);
            if ($current !== $widget && !$context) {
                continue;
            }
            $position = $currentStyle->getPosition();
            $raised = self::isRaised($position);
            if ($top) {
                $levels = [];
                $owners = [];
                $contexts = [];
            }
            // A context in the flow is drawn with it, whatever its z-index
            $levels[] = [$top ? \PHP_INT_MAX : ($raised ? $position->zIndex ?? 0 : max(0, $position->zIndex ?? 0)), $depth + 1];
            $owners[] = $raised ? $current : null;
            $contexts[] = $current;
            if ($current === $widget && $context) {
                $levels[] = [0, $depth + 1];
                $owners[] = null;
                $contexts[] = $current;
            }
        }
        // Under the content of the closest context drawn over the flow around its first negative level, else of the flow;
        // the closest context around that level, whose background it is over
        $under = false;
        $context = null;
        foreach ($levels as $index => [$zIndex]) {
            if ($zIndex < 0) {
                $under = true;
                $context = $contexts[$index - 1] ?? null;
                for ($outer = $index - 1; $outer >= 0; --$outer) {
                    if (null !== $owners[$outer]) {
                        $under = $owners[$outer];
                        break;
                    }
                }
                break;
            }
        }

        return ['path' => $path, 'levels' => $levels, 'under' => $under, 'context' => $context];
    }

    /**
     * Compares two keys: the one drawn first, under the other, is the lower.
     *
     * @param array{path: list<int>, levels: list<array{int, int}>, under: bool|AbstractWidget, context?: AbstractWidget|null} $a
     * @param array{path: list<int>, levels: list<array{int, int}>, under: bool|AbstractWidget, context?: AbstractWidget|null} $b
     */
    public static function compare(array $a, array $b): int
    {
        $count = min(\count($a['levels']), \count($b['levels']));
        for ($i = 0; $i < $count; ++$i) {
            [$zA, $depthA] = $a['levels'][$i];
            [$zB, $depthB] = $b['levels'][$i];
            if (0 !== $order = $zA <=> $zB) {
                return $order;
            }
            // In document order: the paths up to those depths, an ancestor before what it contains
            for ($j = 0, $common = min($depthA, $depthB); $j < $common; ++$j) {
                if (0 !== $order = $a['path'][$j] <=> $b['path'][$j]) {
                    return $order;
                }
            }
            if (0 !== $order = $depthA <=> $depthB) {
                return $order;
            }
        }

        return \count($a['levels']) <=> \count($b['levels']);
    }
}
