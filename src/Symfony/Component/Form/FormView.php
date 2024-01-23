<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Exception\BadMethodCallException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @implements \ArrayAccess<int|string, FormView>
 * @implements \IteratorAggregate<int|string, FormView>
 */
class FormView implements \ArrayAccess, \IteratorAggregate, \Countable
{
    /**
     * The variables assigned to this view.
     */
    public array $vars = [
        'value' => null,
        'attr' => [],
    ];

    /**
     * The parent view.
     */
    public ?self $parent = null;

    /**
     * The child views.
     *
     * @var array<int|string, FormView>
     */
    public array $children = [];

    /**
     * Is the form attached to this renderer rendered?
     *
     * Rendering happens when either the widget or the row method was called.
     * Row implicitly includes widget, however certain rendering mechanisms
     * have to skip widget rendering when a row is rendered.
     */
    private bool $rendered = false;

    private bool $methodRendered = false;

    public function __construct(?self $parent = null)
    {
        $this->parent = $parent;
    }

    /**
     * Returns whether the view was already rendered.
     */
    public function isRendered(): bool
    {
        if (true === $this->rendered || 0 === \count($this->children)) {
            return $this->rendered;
        }

        foreach ($this->children as $child) {
            if (!$child->isRendered()) {
                return false;
            }
        }

        return $this->rendered = true;
    }

    /**
     * Marks the view as rendered.
     *
     * @return $this
     */
    public function setRendered(): static
    {
        $this->rendered = true;

        return $this;
    }

    public function isMethodRendered(): bool
    {
        return $this->methodRendered;
    }

    public function setMethodRendered(): void
    {
        $this->methodRendered = true;
    }

    /**
     * Returns a child by name (implements \ArrayAccess).
     *
     * @param int|string $name The child name
     */
    public function offsetGet(mixed $name): self
    {
        return $this->children[$name];
    }

    /**
     * Returns whether the given child exists (implements \ArrayAccess).
     *
     * @param int|string $name The child name
     */
    public function offsetExists(mixed $name): bool
    {
        return isset($this->children[$name]);
    }

    /**
     * Implements \ArrayAccess.
     *
     * @throws BadMethodCallException always as setting a child by name is not allowed
     */
    public function offsetSet(mixed $name, mixed $value): void
    {
        throw new BadMethodCallException('Not supported.');
    }

    /**
     * Removes a child (implements \ArrayAccess).
     *
     * @param int|string $name The child name
     */
    public function offsetUnset(mixed $name): void
    {
        unset($this->children[$name]);
    }

    /**
     * Returns an iterator to iterate over children (implements \IteratorAggregate).
     *
     * @return \ArrayIterator<int|string, FormView>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->children);
    }

    public function count(): int
    {
        return \count($this->children);
    }
}
