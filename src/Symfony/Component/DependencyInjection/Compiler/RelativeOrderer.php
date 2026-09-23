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
 * Orders identified items using optional "before" and "after" constraints.
 *
 * Items must be passed in fallback order. Missing targets are ignored, and
 * constraints that form a cycle cause an exception.
 */
final class RelativeOrderer
{
    /**
     * @param list<array{id: string, before: string|list<string>, after: string|list<string>}> $orders In fallback order
     *
     * @return list<int> Positions in dependency order
     */
    public static function sort(array $orders): array
    {
        if (!$orders) {
            return [];
        }

        // Avoid building a graph when the fallback order is already the final order.
        if (!array_filter($orders, static fn ($order) => (array) $order['before'] || (array) $order['after'])) {
            return range(0, \count($orders) - 1);
        }

        [$successors, $predecessorCounts] = self::buildGraph($orders);
        $sorted = [];

        // Apply Kahn's topological sorting algorithm. Choosing the first available
        // position on every pass preserves the fallback order wherever the
        // constraints leave a choice.
        while (\count($sorted) < \count($orders)) {
            $next = null;
            foreach ($predecessorCounts as $position => $predecessorCount) {
                // A count of zero means that every item which must precede this
                // one has already been emitted.
                if (0 === $predecessorCount) {
                    $next = $position;
                    break;
                }
            }

            // Every remaining item depends on another remaining item.
            if (null === $next) {
                throw new InvalidArgumentException('Circular "before" or "after" ordering detected.');
            }

            $sorted[] = $next;
            unset($predecessorCounts[$next]);

            // Removing an item satisfies one dependency of each item after it.
            foreach ($successors[$next] ?? [] as $successor => $_) {
                --$predecessorCounts[$successor];
            }
        }

        return $sorted;
    }

    private static function buildGraph(array $orders): array
    {
        $positionsById = $successors = $predecessorCounts = [];

        // An id can occur more than once, so retain every matching position.
        // The predecessor count tracks how many positions must come before each item.
        foreach ($orders as $position => $order) {
            $positionsById[$order['id']][] = $position;
            $predecessorCounts[$position] = 0;
        }

        foreach ($orders as $position => $order) {
            foreach (['before' => $order['before'], 'after' => $order['after']] as $direction => $targets) {
                foreach ((array) $targets as $target) {
                    if (!\is_string($target)) {
                        throw new InvalidArgumentException(\sprintf('The "%s" constraint of item "%s" must contain ids.', $direction, $order['id']));
                    }

                    // Missing ids create no edge, which makes the constraint optional.
                    foreach ($positionsById[$target] ?? [] as $targetPosition) {
                        // An edge points from the item that must run first to the
                        // item that must run second. "after" reverses that edge.
                        $from = 'before' === $direction ? $position : $targetPosition;
                        $to = 'before' === $direction ? $targetPosition : $position;

                        // Ignore a reference to the same occurrence and count
                        // duplicate constraints only once.
                        if ($from === $to || isset($successors[$from][$to])) {
                            continue;
                        }

                        $successors[$from][$to] = true;
                        ++$predecessorCounts[$to];
                    }
                }
            }
        }

        return [$successors, $predecessorCounts];
    }
}
