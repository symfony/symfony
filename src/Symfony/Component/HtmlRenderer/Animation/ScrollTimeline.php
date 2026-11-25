<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Animation;

use Symfony\Component\HtmlRenderer\Css\ValueTokenizer;

/** @internal */
final class ScrollTimeline
{
    public static function source(string $timeline): ?array
    {
        if (!preg_match('/^scroll\(\s*(.*?)\s*\)$/', strtolower(trim($timeline)), $match)) {
            return null;
        }
        $parts = ValueTokenizer::split($match[1]);

        return [array_values(array_intersect($parts, ['nearest', 'root', 'self']))[0] ?? 'nearest', array_values(array_intersect($parts, ['block', 'inline', 'x', 'y']))[0] ?? 'block'];
    }

    public static function scroll(float $offset, float $client, float $content, array $range): ?float
    {
        if (!is_finite($offset) || !is_finite($client) || !is_finite($content) || $client < 0 || $content <= $client) {
            return null;
        }
        $fraction = $offset / ($content - $client);
        [[, $from], [, $to]] = $range;
        if (!is_finite($from) || !is_finite($to)) {
            return null;
        }

        return $to !== $from ? ($fraction * 100 - $from) / ($to - $from) : 0.0;
    }

    public static function view(float $offset, float $client, float $start, float $length, array $inset, array $range, float $minimumViewport = 0.0): ?float
    {
        foreach ([$offset, $client, $start, $length, ...$inset, $minimumViewport] as $value) {
            if (!is_finite($value)) {
                return null;
            }
        }
        $viewport = max($minimumViewport, $client - $inset[0] - $inset[1]);
        $ranges = ['cover' => [$start - $viewport - $inset[1], $start + $length - $inset[1]], 'contain' => $length <= $viewport ? [$start + $length - $viewport - $inset[1], $start - $inset[1]] : [$start - $inset[1], $start + $length - $viewport - $inset[1]], 'entry' => [$start - $viewport - $inset[1], $start + min($length, $viewport) - $viewport - $inset[1]], 'exit' => [$start + max(0, $length - $viewport) - $inset[1], $start + $length - $inset[1]]];
        $ranges['normal'] = $ranges['cover'];
        $ranges['entry-crossing'] = [$start - $viewport - $inset[1], $start + $length - $viewport - $inset[1]];
        $ranges['exit-crossing'] = [$start - $inset[1], $start + $length - $inset[1]];
        $position = static fn (array $edge): float => $ranges[$edge[0]][0] + ($ranges[$edge[0]][1] - $ranges[$edge[0]][0]) * $edge[1] / 100;
        $from = $position($range[0]);
        $to = $position($range[1]);
        if (!is_finite($from) || !is_finite($to)) {
            return null;
        }

        return $to !== $from ? ($offset - $from) / ($to - $from) : ($offset >= $to ? 1.0 : 0.0);
    }

    public static function named(object $subject, string $name, \Closure $style, \Closure $parent, \Closure $children): ?array
    {
        $defines = static function (object $candidate) use ($name, $style): ?array {
            $values = $style($candidate);
            foreach (['scroll' => false, 'view' => true] as $kind => $view) {
                $parts = ValueTokenizer::split($values[$kind.'-timeline'] ?? '');
                $names = isset($values[$kind.'-timeline-name']) ? array_map('trim', explode(',', $values[$kind.'-timeline-name'])) : [$parts[0] ?? ''];
                if (\in_array($name, $names, true)) {
                    return [$candidate, $view, strtolower(trim($values[$kind.'-timeline-axis'] ?? $parts[1] ?? 'block')), $view ? ($values['view-timeline-inset'] ?? implode(' ', \array_slice($parts, 2))) : ''];
                }
            }

            return null;
        };
        $search = static function (object $root, int $depth = 0) use (&$search, $defines, $children): ?array {
            if (null !== $found = $defines($root)) {
                return $found;
            }
            foreach ($depth < 32 ? $children($root) : [] as $child) {
                if (null !== $found = $search($child, $depth + 1)) {
                    return $found;
                }
            }

            return null;
        };
        for ($current = $subject; null !== $current; $current = $parent($current)) {
            if (null !== $found = $defines($current)) {
                return $found;
            }
            $scope = $style($current)['timeline-scope'] ?? '';
            if ('all' === strtolower(trim($scope)) || \in_array($name, array_map('trim', explode(',', $scope)), true)) {
                if (null !== $found = $search($current)) {
                    return $found;
                }
            }
        }

        return null;
    }

    public static function fraction(\Dom\Element $element, Animation $animation, array $ownStyle, \SplObjectStorage $styles, ?\SplObjectStorage $containers, ?\SplObjectStorage $styleParents = null, ?\Closure $length = null, ?\Closure $viewport = null): ?float
    {
        if (null === $containers) {
            return null;
        }
        $tree = $element->getAttribute(\Symfony\Component\HtmlRenderer\Css\Selector::TREE_ATTRIBUTE);
        $parent = static function (\Dom\Element $node) use ($styleParents, $tree): ?\Dom\Element {
            $parent = $styleParents[$node] ?? $node->parentElement;

            return null !== $parent && $tree === $parent->getAttribute(\Symfony\Component\HtmlRenderer\Css\Selector::TREE_ATTRIBUTE) ? $parent : null;
        };
        $style = static fn (\Dom\Element $node): array => $node === $element ? $ownStyle : ($styles[$node] ?? ($containers[$node] ?? [])['style'] ?? []);
        $subject = $element;
        $view = false;
        $inset = '';
        if (null !== $source = self::source($animation->timeline)) {
            [$kind, $axis] = $source;
            $candidate = match ($kind) {
                'self' => $element,
                'root' => $element->ownerDocument->documentElement,
                default => $parent($element),
            };
        } elseif (preg_match('/^view\(\s*(.*?)\s*\)$/', $animation->timeline, $match)) {
            $parts = ValueTokenizer::split($match[1]);
            $axis = array_values(array_intersect($parts, ['block', 'inline', 'x', 'y']))[0] ?? 'block';
            $inset = implode(' ', array_diff($parts, ['block', 'inline', 'x', 'y']));
            $view = true;
            $kind = 'nearest';
            $candidate = $parent($subject);
        } elseif (str_starts_with($animation->timeline, '--') && null !== $named = self::named($element, $animation->timeline, $style, $parent, static function (\Dom\Element $node) use ($tree): array {
            $children = [];
            for ($child = $node->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
                if ($tree === $child->getAttribute(\Symfony\Component\HtmlRenderer\Css\Selector::TREE_ATTRIBUTE)) {
                    $children[] = $child;
                }
            }

            return $children;
        })) {
            [$subject, $view, $axis, $inset] = $named;
            $kind = $view ? 'nearest' : 'self';
            $candidate = $view ? $parent($subject) : $subject;
        } else {
            return null;
        }
        for (; null !== $candidate; $candidate = $parent($candidate)) {
            $entry = $containers[$candidate] ?? [];
            $values = $style($candidate);
            $verticalMode = 'horizontal-tb' !== ($values['writing-mode'] ?? 'horizontal-tb');
            $vertical = 'y' === $axis || 'block' === $axis && !$verticalMode || 'inline' === $axis && $verticalMode;
            $overflow = $values[$vertical ? 'overflow-y' : 'overflow-x'] ?? $values['overflow'] ?? 'visible';
            if (\in_array($overflow, ['hidden', 'auto', 'scroll'], true)) {
                $metrics = $entry['scroll'] ?? [];
                $client = $metrics[$vertical ? 'client-height' : 'client-width'] ?? null;
                $content = $metrics[$vertical ? 'content-height' : 'content-width'] ?? null;
                $offset = $metrics[$vertical ? 'offset-y' : 'offset-x'] ?? null;
                if (null === $client || null === $content || null === $offset) {
                    return null;
                }
                if (!$view) {
                    return self::scroll($offset, $client, $content, $animation->range);
                }
                $subjectEntry = $containers[$subject] ?? [];
                $bounds = $subjectEntry['bounds'] ?? null;
                $position = $subjectEntry['scroll'][$vertical ? 'view-offset-y' : 'view-offset-x'] ?? null;
                if (null === $position && null !== $bounds && isset($metrics[$vertical ? 'port-y' : 'port-x'])) {
                    $position = [$bounds[$vertical ? 1 : 0] - $metrics[$vertical ? 'port-y' : 'port-x'] + $offset, $bounds[$vertical ? 3 : 2]];
                }
                if (null === $position) {
                    return null;
                }
                $parts = ValueTokenizer::split($inset);
                $resolve = static fn (string $value): float => 'auto' === $value || null === $length ? 0.0 : max(0.0, $length($value, $vertical, $client, $subjectEntry['style'] ?? $style($subject)) ?? 0.0);
                $first = $resolve($parts[0] ?? 'auto');
                $last = $resolve($parts[1] ?? $parts[0] ?? 'auto');

                return self::view($offset, $client, $position[0], $position[1], [$first, $last], $animation->range, null === $viewport ? 0.0 : $viewport(max(0.0, $client - $first - $last), $vertical));
            }
            if ('nearest' !== $kind) {
                return null;
            }
        }

        return null;
    }
}
