<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping;

/**
 * Specifies whether and how a traversable object should be traversed.
 *
 * If the node traverser traverses a node whose value is an instance of
 * {@link \Traversable}, and if that node is either a class node or if
 * cascading is enabled, then the node's traversal strategy will be checked.
 * Depending on the requested traversal strategy, the node traverser will
 * iterate over the object and cascade each object or collection returned by
 * the iterator.
 *
 * The traversal strategy is ignored for arrays. Arrays are always iterated.
 *
 * @since  2.1
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see CascadingStrategy
 */
class TraversalStrategy
{
    /**
     * Specifies that a node's value should be iterated only if it is an
     * instance of {@link \Traversable}.
     */
    const IMPLICIT = 1;

    /**
     * Specifies that a node's value should never be iterated.
     */
    const NONE = 2;

    /**
     * Specifies that a node's value should always be iterated. If the value is
     * not an instance of {@link \Traversable}, an exception should be thrown.
     */
    const TRAVERSE = 4;

    /**
     * Specifies that nested instances of {@link \Traversable} should never be
     * iterated. Can be combined with {@link IMPLICIT} or {@link TRAVERSE}.
     *
     * @deprecated This constant was added for backwards compatibility only.
     *             It will be removed in Symfony 3.0.
     */
    const STOP_RECURSION = 8;

    /**
     * Not instantiable.
     */
    private function __construct()
    {
    }
}
