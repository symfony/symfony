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
    public $vars = [
        'value' => null,
        'attr' => [],
    ];

    /**
     * The parent view.
     */
    public $parent;

    /**
     * The child views.
     *
     * @var array<int|string, FormView>
     */
    public $children = [];

    /**
     * Is the form attached to this renderer rendered?
     *
     * Rendering happens when either the widget or the row method was called.
     * Row implicitly includes widget, however certain rendering mechanisms
     * have to skip widget rendering when a row is rendered.
     *
     * @var bool
     */
    private $rendered = false;

    private $methodRendered = false;

    public function __construct(self $parent = null)
    {
        $this->parent = $parent;
    }

    /**
     * Returns whether the view was already rendered.
     *
     * @return bool
     */
    public function isRendered()
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
    public function setRendered()
    {
        $this->rendered = true;

        return $this;
    }

    /**
     * @return bool
     */
    public function isMethodRendered()
    {
        return $this->methodRendered;
    }

    public function setMethodRendered()
    {
        $this->methodRendered = true;
    }

    /**
     * Returns a child by name (implements \ArrayAccess).
     *
     * @param int|string $name The child name
     *
     * @return self
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($name)
    {
        return $this->children[$name];
    }

    /**
     * Returns whether the given child exists (implements \ArrayAccess).
     *
     * @param int|string $name The child name
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($name)
    {
        return isset($this->children[$name]);
    }

    /**
     * Implements \ArrayAccess.
     *
     * @return void
     *
     * @throws BadMethodCallException always as setting a child by name is not allowed
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($name, $value)
    {
        throw new BadMethodCallException('Not supported.');
    }

    /**
     * Removes a child (implements \ArrayAccess).
     *
     * @param int|string $name The child name
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($name)
    {
        unset($this->children[$name]);
    }

    /**
     * Returns an iterator to iterate over children (implements \IteratorAggregate).
     *
     * @return \ArrayIterator<int|string, FormView>
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator($this->children);
    }

    /**
     * Implements \Countable.
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return \count($this->children);
    }
}
