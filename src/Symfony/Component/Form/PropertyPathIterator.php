<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Traverses a property path and provides additional methods to find out
 * information about the current element
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class PropertyPathIterator extends \ArrayIterator
{
    /**
     * The traversed property path
     * @var PropertyPath
     */
    protected $path;

    /**
     * Constructor.
     *
     * @param PropertyPath $path  The property path to traverse
     */
    public function __construct(PropertyPath $path)
    {
        parent::__construct($path->getElements());

        $this->path = $path;
    }

    /**
     * Returns whether next() can be called without making the iterator invalid
     *
     * @return boolean
     */
    public function hasNext()
    {
        return $this->offsetExists($this->key() + 1);
    }

    /**
     * Returns whether the current element in the property path is an array
     * index
     *
     * @return boolean
     */
    public function isIndex()
    {
        return $this->path->isIndex($this->key());
    }

    /**
     * Returns whether the current element in the property path is a property
     * names
     *
     * @return boolean
     */
    public function isProperty()
    {
        return $this->path->isProperty($this->key());
    }
}