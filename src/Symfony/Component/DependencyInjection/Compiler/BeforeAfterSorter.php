<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Reorders items to satisfy "before" and "after" constraints, keeping the seed order everywhere else.
 *
 * Items are emitted depth-first in seed order, which keeps the seed order intact wherever the
 * constraints allow it. "A before B" and "B after A" describe the same edge and yield the same
 * order. References to items that are not in the seed are ignored: the package declaring them may
 * simply not be installed. An item referencing itself is ignored too.
 * Callers can give the two kinds of constraints the names that fit their domain, like "within" and "around" for decorators.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class BeforeAfterSorter
{
    /**
     * @param list<string>                               $seed        Items in the order they would have without any constraint
     * @param array<string, array<string, list<string>>> $constraints Items mapped to the items they go before and after, listed under the $before and $after keys
     * @param array<string, list<string>>                $aliases     Alternative names, each designating the items it stands for; a name that is not listed here designates the item bearing it
     * @param string                                     $before      The name of the constraints listing the items an item goes before
     * @param string                                     $after       The name of the constraints listing the items an item goes after
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException when the constraints are cyclic
     */
    public static function sort(array $seed, array $constraints, array $aliases = [], string $before = 'before', string $after = 'after'): array
    {
        if (!$constraints) {
            return $seed;
        }

        $predecessors = array_fill_keys($seed, []);

        foreach ($constraints as $item => $constraint) {
            if (!isset($predecessors[$item])) {
                continue;
            }

            foreach ([$before, $after] as $direction) {
                foreach ($constraint[$direction] ?? [] as $target) {
                    foreach ($aliases[$target] ?? [$target] as $targetItem) {
                        if ($targetItem === $item || !isset($predecessors[$targetItem])) {
                            continue;
                        }

                        if ($before === $direction) {
                            $predecessors[$targetItem][] = $item;
                        } else {
                            $predecessors[$item][] = $targetItem;
                        }
                    }
                }
            }
        }

        $sorted = [];
        $states = [];

        foreach ($seed as $item) {
            self::visit($item, $predecessors, $states, $sorted, [], $before, $after);
        }

        return $sorted;
    }

    /**
     * Sorts items by priority first, then applies the constraints.
     *
     * An explicit priority is a claim: constraints reorder such an item only among the items sharing its
     * priority, and a constraint that would need it to cross a priority is an error. An item without priority
     * is free: its constraints place it, and it adopts the priority its place requires, 0 when that fits.
     *
     * @param array<string, int|null>                    $priorities  Items in their default order, mapped to their declared priority, null when none was declared
     * @param array<string, array<string, list<string>>> $constraints See sort()
     * @param array<string, list<string>>                $aliases     See sort()
     * @param string                                     $before      See sort()
     * @param string                                     $after       See sort()
     *
     * @return array<string, int> The items in their final order, mapped to their effective priority
     *
     * @throws InvalidArgumentException when the constraints are cyclic or contradict an explicit priority
     */
    public static function sortWithPriorities(array $priorities, array $constraints, array $aliases = [], string $before = 'before', string $after = 'after'): array
    {
        $seed = array_keys($priorities);
        $indexes = array_flip($seed);
        usort($seed, static fn ($a, $b) => ($priorities[$b] ?? 0) <=> ($priorities[$a] ?? 0) ?: $indexes[$a] <=> $indexes[$b]);

        if (!$constraints) {
            return array_combine($seed, array_map(static fn ($item) => $priorities[$item] ?? 0, $seed));
        }

        // detects cycles before the bounds below are computed, so that a cycle is reported as such
        self::sort($seed, $constraints, $aliases, $before, $after);

        // every edge is [$first, $second] with $first going before $second; a declared priority must already agree with it
        $edges = [];

        foreach ($constraints as $item => $constraint) {
            if (!isset($indexes[$item])) {
                continue;
            }

            foreach ([$before, $after] as $direction) {
                foreach ($constraint[$direction] ?? [] as $target) {
                    foreach ($aliases[$target] ?? [$target] as $targetItem) {
                        if ($targetItem === $item || !isset($indexes[$targetItem])) {
                            continue;
                        }

                        $edges[] = $before === $direction ? [$item, $targetItem] : [$targetItem, $item];

                        if (null === $priority = $priorities[$item] ?? null) {
                            continue;
                        }

                        if (null === $targetPriority = $priorities[$targetItem] ?? null) {
                            continue;
                        }

                        if ($before === $direction && $priority < $targetPriority) {
                            throw new InvalidArgumentException(\sprintf('The priority of "%s" (%d) contradicts its "%s" constraint on "%s" (%d): raise it to %d or more, remove it, or drop the constraint.', $item, $priority, $before, $targetItem, $targetPriority, $targetPriority));
                        }

                        if ($after === $direction && $priority > $targetPriority) {
                            throw new InvalidArgumentException(\sprintf('The priority of "%s" (%d) contradicts its "%s" constraint on "%s" (%d): lower it to %d or less, remove it, or drop the constraint.', $item, $priority, $after, $targetItem, $targetPriority, $targetPriority));
                        }
                    }
                }
            }
        }

        // an item without priority is bounded by the items it must run before (from below) and after (from above),
        // through other free items too, so the bounds are propagated until they settle
        $lows = $highs = $lowsFrom = $highsFrom = [];

        foreach ($priorities as $item => $priority) {
            if (null === $priority) {
                $lows[$item] = \PHP_INT_MIN;
                $highs[$item] = \PHP_INT_MAX;
            }
        }

        do {
            $settled = true;

            foreach ($edges as [$first, $second]) {
                if (isset($lows[$first]) && ($low = $priorities[$second] ?? $lows[$second]) > $lows[$first]) {
                    $lows[$first] = $low;
                    $lowsFrom[$first] = $second;
                    $settled = false;
                }

                if (isset($highs[$second]) && ($high = $priorities[$first] ?? $highs[$first]) < $highs[$second]) {
                    $highs[$second] = $high;
                    $highsFrom[$second] = $first;
                    $settled = false;
                }
            }
        } while (!$settled);

        $resolved = [];

        foreach ($priorities as $item => $priority) {
            if (null !== $priority) {
                $resolved[$item] = $priority;
            } elseif ($lows[$item] > $highs[$item]) {
                throw new InvalidArgumentException(\sprintf('The "%s"/"%s" constraints on "%s" cannot be satisfied: it would need a priority of at least %d to run '.$before.' "%s" and at most %d to run '.$after.' "%s".', $before, $after, $item, $lows[$item], $lowsFrom[$item], $highs[$item], $highsFrom[$item]));
            } else {
                $resolved[$item] = min($highs[$item], max($lows[$item], 0));
            }
        }

        // with every item in the bucket its constraints allow, the sort only reorders inside buckets
        usort($seed, static fn ($a, $b) => $resolved[$b] <=> $resolved[$a] ?: $indexes[$a] <=> $indexes[$b]);
        $sorted = self::sort($seed, $constraints, $aliases, $before, $after);

        $previous = null;
        foreach ($sorted as $item) {
            if (null !== $previous && $resolved[$item] > $resolved[$previous]) {
                throw new InvalidArgumentException(\sprintf('The "%s"/"%s" constraints put "%s" (priority %d) ahead of "%s" (priority %d), which their priorities do not allow.', $before, $after, $previous, $resolved[$previous], $item, $resolved[$item]));
            }

            $previous = $item;
        }

        return array_combine($sorted, array_map(static fn ($item) => $resolved[$item], $sorted));
    }

    /**
     * @param array<string, list<string>> $predecessors
     * @param array<string, int>          $states
     * @param list<string>                $sorted
     * @param list<string>                $path
     */
    private static function visit(string $item, array $predecessors, array &$states, array &$sorted, array $path, string $before, string $after): void
    {
        if (2 === ($states[$item] ?? 0)) {
            return;
        }

        if (1 === ($states[$item] ?? 0)) {
            $cycle = \array_slice($path, array_search($item, $path, true));
            $cycle[] = $item;

            throw new InvalidArgumentException(\sprintf('Cycle detected in the "%s"/"%s" constraints: "%s".', $before, $after, implode('" -> "', $cycle)));
        }

        $states[$item] = 1;
        $path[] = $item;

        foreach ($predecessors[$item] as $predecessor) {
            self::visit($predecessor, $predecessors, $states, $sorted, $path, $before, $after);
        }

        $states[$item] = 2;
        $sorted[] = $item;
    }
}
