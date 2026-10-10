<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug\Section;

use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;
use Symfony\Bundle\FrameworkBundle\Debug\DebugSectionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Base class for debug sections backed by a memoized, in-memory item list.
 *
 * Sections only build their full item list (once) in buildItems(); searching,
 * filtering and result ranking are shared and rely on the label, value and
 * searchText of each item (see DebugItem::matches()).
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @experimental
 */
abstract class AbstractDebugSection implements DebugSectionInterface
{
    /**
     * Separates the parts of composite item values (e.g. "dispatcher\0event").
     */
    protected const string VALUE_SEPARATOR = "\0";

    /** @var list<DebugItem>|null */
    private ?array $items = null;

    public function search(string $query): array
    {
        return $this->filter($this->items ??= $this->buildItems(), $query);
    }

    public function filter(array $items, string $query): array
    {
        if ('' === $query) {
            return $items;
        }

        $matches = array_values(array_filter($items, static fn (DebugItem $item): bool => $item->matches($query)));

        // rank items matching on their label before items matching only on their value
        // or extra search text, without breaking the contiguity of item groups (the
        // list renders one header per consecutive run of items sharing a group)
        $groupOrder = [];
        foreach ($matches as $item) {
            $groupOrder[$item->group ?? ''] ??= \count($groupOrder);
        }
        usort($matches, static fn (DebugItem $a, DebugItem $b): int => [$groupOrder[$a->group ?? ''], false === stripos($a->label, $query)] <=> [$groupOrder[$b->group ?? ''], false === stripos($b->label, $query)]);

        return $matches;
    }

    /**
     * Builds the full, unfiltered item list once. Recomputing it on every search
     * would be costly on large applications.
     *
     * @return list<DebugItem>
     */
    abstract protected function buildItems(): array;

    /**
     * Renders console-style output into a decorated string, the way the
     * "debug:*" descriptors do.
     *
     * @param \Closure(SymfonyStyle): void $describe
     */
    protected function describeToBuffer(\Closure $describe): string
    {
        $buffer = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $describe(new SymfonyStyle(new ArrayInput([]), $buffer));

        return $buffer->fetch();
    }
}
