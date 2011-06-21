<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Util;

/**
 * Traverses a property path and provides additional methods to find out
 * information about the current element
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
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
     * @return Boolean
     */
    public function hasNext()
    {
        return $this->offsetExists($this->key() + 1);
    }

    /**
     * Returns whether the current element in the property path is an array
     * index
     *
     * @return Boolean
     */
    public function isIndex()
    {
        return $this->path->isIndex($this->key());
    }

    /**
     * Returns whether the current element in the property path is a property
     * names
     *
     * @return Boolean
     */
    public function isProperty()
    {
        return $this->path->isProperty($this->key());
    }
}
