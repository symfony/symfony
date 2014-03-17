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
use Symfony\Component\Validator\Mapping\PropertyMetadataInterface;
use Symfony\Component\Validator\Mapping\TraversalStrategy;

/**
 * Represents the value of a property and its associated metadata.
 *
 * If the property contains an object and should be cascaded, a new
 * {@link ClassNode} instance will be created for that object:
 *
 *     (Article:ClassNode)
 *                \
 *        (->author:PropertyNode)
 *                  \
 *            (Author:ClassNode)
 *
 * If the property contains a collection which should be traversed, a new
 * {@link CollectionNode} instance will be created for that collection:
 *
 *     (Article:ClassNode)
 *                \
 *        (->tags:PropertyNode)
 *                  \
 *           (array:CollectionNode)
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyNode extends Node
{
    /**
     * @var PropertyMetadataInterface
     */
    public $metadata;

    /**
     * Creates a new property node.
     *
     * @param object                    $object         The object the property
     *                                                  belongs to
     * @param mixed                     $value          The property value
     * @param PropertyMetadataInterface $metadata       The property's metadata
     * @param string                    $propertyPath   The property path leading
     *                                                  to this node
     * @param string[]                  $groups         The groups in which this
     *                                                  node should be validated
     * @param string[]|null             $cascadedGroups The groups in which
     *                                                  cascaded objects should
     *                                                  be validated
     * @param integer                   $traversalStrategy
     *
     * @throws UnexpectedTypeException If $object is not an object
     *
     * @see \Symfony\Component\Validator\Mapping\TraversalStrategy
     */
    public function __construct($value, $cacheKey, PropertyMetadataInterface $metadata, $propertyPath, array $groups, $cascadedGroups = null, $traversalStrategy = TraversalStrategy::IMPLICIT)
    {
        parent::__construct(
            $value,
            $cacheKey,
            $metadata,
            $propertyPath,
            $groups,
            $cascadedGroups,
            $traversalStrategy
        );
    }

}
