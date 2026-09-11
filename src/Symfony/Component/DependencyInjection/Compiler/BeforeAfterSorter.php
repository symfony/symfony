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
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class BeforeAfterSorter
{
    /**
     * @param list<string>                                                      $seed        Items in the order they would have without any constraint
     * @param array<string, array{before?: list<string>, after?: list<string>}> $constraints
     * @param array<string, list<string>>                                       $aliases     Alternative names, each designating the items it stands for; a name that is not listed here designates the item bearing it
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException when the constraints are cyclic
     */
    public static function sort(array $seed, array $constraints, array $aliases = []): array
    {
        if (!$constraints) {
            return $seed;
        }

        $predecessors = array_fill_keys($seed, []);

        foreach ($constraints as $item => $constraint) {
            if (!isset($predecessors[$item])) {
                continue;
            }

            foreach (['before', 'after'] as $direction) {
                foreach ($constraint[$direction] ?? [] as $target) {
                    foreach ($aliases[$target] ?? [$target] as $targetItem) {
                        if ($targetItem === $item || !isset($predecessors[$targetItem])) {
                            continue;
                        }

                        if ('before' === $direction) {
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
            self::visit($item, $predecessors, $states, $sorted, []);
        }

        return $sorted;
    }

    /**
     * @param array<string, list<string>> $predecessors
     * @param array<string, int>          $states
     * @param list<string>                $sorted
     * @param list<string>                $path
     */
    private static function visit(string $item, array $predecessors, array &$states, array &$sorted, array $path): void
    {
        if (2 === ($states[$item] ?? 0)) {
            return;
        }

        if (1 === ($states[$item] ?? 0)) {
            $cycle = \array_slice($path, array_search($item, $path, true));
            $cycle[] = $item;

            throw new InvalidArgumentException(\sprintf('Cycle detected in the "before"/"after" constraints: "%s".', implode('" -> "', $cycle)));
        }

        $states[$item] = 1;
        $path[] = $item;

        foreach ($predecessors[$item] as $predecessor) {
            self::visit($predecessor, $predecessors, $states, $sorted, $path);
        }

        $states[$item] = 2;
        $sorted[] = $item;
    }
}
