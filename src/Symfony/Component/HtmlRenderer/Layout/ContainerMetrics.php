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

/** @internal Per-element principal dimensions and the actual scrolling viewport. */
final class ContainerMetrics
{
    public static function collect(\SplObjectStorage $styles, Fragment $root, \SplObjectStorage $states): \SplObjectStorage
    {
        $containers = new \SplObjectStorage();
        foreach ($styles as $element) {
            $containers[$element] = ['style' => $styles[$element]];
        }
        $groups = new \SplObjectStorage();
        $ports = new \SplObjectStorage();
        $snapped = new \SplObjectStorage();
        $visit = static function (Fragment $fragment) use (&$visit, $containers, $states, $groups, $ports, $snapped): void {
            $element = $fragment->node;
            if ($element instanceof \Dom\Element && null === $fragment->pseudoElement && !\array_key_exists('width', $containers[$element] ?? [])) {
                $content = $fragment->getContentBox();
                $entry = $containers[$element] ?? ['style' => []];
                $entry['bounds'] = [$fragment->bounds->x, $fragment->bounds->y, $fragment->bounds->width, $fragment->bounds->height];
                $entry['width'] = $content->width;
                $entry['height'] = $content->height;
                $entry['intrinsic-height'] = max([0.0, ...array_map(static fn (Fragment $child): float => \in_array($child->style['position'] ?? 'static', ['absolute', 'fixed'], true) ? 0.0 : $child->bounds->y + $child->bounds->height - $content->y, $fragment->children)]);
                $entry['scroll'] = self::scrollState($fragment, $states);
                $containers[$element] = $entry;
            }
            if ($element instanceof \Dom\Element && null === $fragment->pseudoElement && null !== $fragment->scroll) {
                $entry = $containers[$element];
                $entry['scroll'] = self::scrollState($fragment, $states);
                $containers[$element] = $entry;
                $ports[$element] = $fragment->scroll;
                foreach ($fragment->scroll->snapped as $axis => $targets) {
                    foreach ($targets as $target) {
                        $axes = $snapped[$target] ?? [];
                        $axes[] = $axis;
                        $snapped[$target] = array_values(array_unique($axes));
                    }
                }
            }
            if ($element instanceof \Dom\Element && 'scroll-marker-group' === $fragment->pseudoElement) {
                $targets = new \SplObjectStorage();
                $markers = static function (Fragment $marker) use (&$markers, $targets): void {
                    if ($marker->node instanceof \Dom\Element && 'scroll-marker' === $marker->pseudoElement) {
                        $targets[$marker->node] = true;
                    }
                    foreach ($marker->children as $child) {
                        $markers($child);
                    }
                };
                $markers($fragment);
                $groups[$element] = $targets;
            }
            foreach ($fragment->children as $child) {
                $visit($child);
            }
        };
        $visit($root);
        foreach ($snapped as $target) {
            $entry = $containers[$target] ?? ['style' => []];
            $entry['scroll']['snapped'] = $snapped[$target];
            $containers[$target] = $entry;
        }
        foreach ($groups as $owner) {
            if (!isset($ports[$owner])) {
                continue;
            }
            $scroll = $ports[$owner];
            $horizontal = $scroll->maxX > 0;
            $starts = $targets = [];
            foreach ($groups[$owner] as $target) {
                $targets[] = $target;
                $bounds = ($containers[$target] ?? [])['bounds'] ?? null;
                $starts[] = null === $bounds ? null : ($horizontal ? $bounds[0] - $scroll->port->x + $scroll->offsetX : $bounds[1] - $scroll->port->y + $scroll->offsetY);
            }
            $current = ScrollMarkerGeometry::current($starts, $horizontal ? $scroll->offsetX : $scroll->offsetY, $horizontal ? $scroll->port->width + $scroll->maxX : $scroll->port->height + $scroll->maxY, $horizontal ? $scroll->port->width : $scroll->port->height);
            foreach ($targets as $index => $target) {
                $entry = $containers[$target] ?? ['style' => []];
                $entry['scroll']['target-current'] = $index === $current;
                $containers[$target] = $entry;
            }
        }

        return $containers;
    }

    private static function scrollState(Fragment $fragment, \SplObjectStorage $states): array
    {
        $element = $fragment->node;
        $scrollState = ['scrollable' => [], 'scrolled' => ($states[$element] ?? [])['scroll-direction'] ?? []];
        if (null !== $scroll = $fragment->scroll) {
            $scrollState += ['port-x' => $scroll->port->x, 'port-y' => $scroll->port->y, 'client-width' => $scroll->port->width, 'client-height' => $scroll->port->height, 'content-width' => $scroll->port->width + $scroll->maxX, 'content-height' => $scroll->port->height + $scroll->maxY, 'offset-x' => $scroll->offsetX, 'offset-y' => $scroll->offsetY];
            foreach (['left' => $scroll->offsetX > 0, 'right' => $scroll->offsetX < $scroll->maxX, 'top' => $scroll->offsetY > 0, 'bottom' => $scroll->offsetY < $scroll->maxY] as $side => $available) {
                if ($available) {
                    $scrollState['scrollable'][] = $side;
                }
            }
        }

        return $scrollState;
    }

    public static function fingerprint(\SplObjectStorage $containers): string
    {
        $values = [];
        foreach ($containers as $element) {
            $values[] = [spl_object_id($element), self::fingerprintValues($containers[$element])];
        }

        return hash('xxh128', serialize($values));
    }

    private static function fingerprintValues(mixed $value): mixed
    {
        if (\is_float($value)) {
            return 'f'.pack('E', $value);
        }
        if (\is_string($value)) {
            return 's'.$value;
        }
        if (\is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::fingerprintValues($item);
            }
        }

        return $value;
    }
}
