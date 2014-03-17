<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Node;

use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\TraversalStrategy;

/**
 * Represents an object and its class metadata in the validation graph.
 *
 * If the object is a collection which should be traversed, a new
 * {@link CollectionNode} instance will be created for that object:
 *
 *     (TagList:ClassNode)
 *                \
 *        (TagList:CollectionNode)
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ClassNode extends Node
{
    /**
     * @var ClassMetadataInterface
     */
    public $metadata;

    /**
     * Creates a new class node.
     *
     * @param object                 $object            The validated object
     * @param ClassMetadataInterface $metadata          The class metadata of
     *                                                  that object
     * @param string                 $propertyPath      The property path leading
     *                                                  to this node
     * @param string[]               $groups            The groups in which this
     *                                                  node should be validated
     * @param string[]|null          $cascadedGroups    The groups in which
     *                                                  cascaded objects should
     *                                                  be validated
     * @param integer                $traversalStrategy The strategy used for
     *                                                  traversing the object
     *
     * @throws UnexpectedTypeException If $object is not an object
     *
     * @see \Symfony\Component\Validator\Mapping\TraversalStrategy
     */
    public function __construct($object, $cacheKey, ClassMetadataInterface $metadata, $propertyPath, array $groups, $cascadedGroups = null, $traversalStrategy = TraversalStrategy::IMPLICIT)
    {
        if (!is_object($object)) {
            throw new UnexpectedTypeException($object, 'object');
        }

        parent::__construct(
            $object,
            $cacheKey,
            $metadata,
            $propertyPath,
            $groups,
            $cascadedGroups,
            $traversalStrategy
        );
    }
}
