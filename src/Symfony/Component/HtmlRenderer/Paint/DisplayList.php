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

/**
 * @implements \IteratorAggregate<int, array<string, mixed>>
 */
final readonly class DisplayList implements \IteratorAggregate, \Countable
{
    /**
     * @param list<array<string, mixed>> $operations
     */
    public function __construct(private array $operations = [])
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getIterator(): \Traversable
    {
        yield from $this->operations;
    }

    public function count(): int
    {
        return \count($this->operations);
    }
}
