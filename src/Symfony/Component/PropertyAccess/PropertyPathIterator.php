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
 * information about the current element
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @since v2.2.0
 */
class PropertyPathIterator extends \ArrayIterator implements PropertyPathIteratorInterface
{
    /**
     * The traversed property path
     * @var PropertyPathInterface
     */
    protected $path;

    /**
     * Constructor.
     *
     * @param PropertyPathInterface $path The property path to traverse
     *
     * @since v2.2.0
     */
    public function __construct(PropertyPathInterface $path)
    {
        parent::__construct($path->getElements());

        $this->path = $path;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.2.0
     */
    public function isIndex()
    {
        return $this->path->isIndex($this->key());
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.2.0
     */
    public function isProperty()
    {
        return $this->path->isProperty($this->key());
    }
}
