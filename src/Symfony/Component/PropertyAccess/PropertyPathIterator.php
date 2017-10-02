<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess;

/**
 * Traverses a property path and provides additional methods to find out
 * information about the current element.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyPathIterator extends \ArrayIterator implements PropertyPathIteratorInterface
{
    /**
     * The traversed property path.
     *
     * @var PropertyPathInterface
     */
    protected $path;

    /**
     * @param PropertyPathInterface $path The property path to traverse
     */
    public function __construct(PropertyPathInterface $path)
    {
        parent::__construct($path->getElements());

        $this->path = $path;
    }

    /**
     * {@inheritdoc}
     */
    public function isIndex()
    {
        return $this->path->isIndex($this->key());
    }

    /**
     * {@inheritdoc}
     */
    public function isProperty()
    {
        return $this->path->isProperty($this->key());
    }
}
