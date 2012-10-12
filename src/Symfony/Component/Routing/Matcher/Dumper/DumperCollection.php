<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher\Dumper;

/**
 * Collection of routes.
 *
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class DumperCollection implements \IteratorAggregate
{
    private $parent;
    private $children = array();

    /**
     * Returns the children routes and collections.
     *
     * @return array Array of DumperCollection|DumperRoute
     */
    public function all()
    {
        return $this->children;
    }

    /**
     * Adds a route or collection
     *
     * @param DumperRoute|DumperCollection The route or collection
     */
    public function add($child)
    {
        if ($child instanceof DumperCollection) {
            $child->setParent($this);
        }
        $this->children[] = $child;
    }

    /**
     * Sets children
     *
     * @param array $children The children
     */
    public function setAll(array $children)
    {
        foreach ($children as $child) {
            if ($child instanceof DumperCollection) {
                $child->setParent($this);
            }
        }
        $this->children = $children;
    }

    /**
     * Returns an iterator over the children.
     *
     * @return \Iterator The iterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->children);
    }

    /**
     * Returns the root of the collection.
     *
     * @return DumperCollection The root collection
     */
    public function getRoot()
    {
        return (null !== $this->parent) ? $this->parent->getRoot() : $this;
    }

    /**
     * Returns the parent collection.
     *
     * @return DumperCollection|null The parent collection or null if the collection has no parent
     */
    protected function getParent()
    {
        return $this->parent;
    }

    /**
     * Sets the parent collection.
     *
     * @param DumperCollection $parent The parent collection
     */
    protected function setParent(DumperCollection $parent)
    {
        $this->parent = $parent;
    }
}
