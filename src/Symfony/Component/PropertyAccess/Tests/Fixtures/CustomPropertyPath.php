<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

use Symfony\Component\PropertyAccess\PropertyPathInterface;

/**
 * A property path whose elements are array indices that may contain dots.
 */
class CustomPropertyPath implements \IteratorAggregate, PropertyPathInterface
{
    /**
     * @param list<string> $elements
     */
    public function __construct(
        private array $elements,
    ) {
    }

    public function __toString(): string
    {
        return implode('.', $this->elements);
    }

    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->elements);
    }

    public function getLength(): int
    {
        return \count($this->elements);
    }

    public function getParent(): ?PropertyPathInterface
    {
        return 1 < \count($this->elements) ? new self(\array_slice($this->elements, 0, -1)) : null;
    }

    public function getElements(): array
    {
        return $this->elements;
    }

    public function getElement(int $index): string
    {
        return $this->elements[$index];
    }

    public function isProperty(int $index): bool
    {
        return false;
    }

    public function isIndex(int $index): bool
    {
        return true;
    }

    public function isNullSafe(int $index): bool
    {
        return false;
    }
}
