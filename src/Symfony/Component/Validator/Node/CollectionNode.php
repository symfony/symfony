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
use Symfony\Component\Validator\Mapping\MetadataInterface;

/**
 * Represents an traversable collection in the validation graph.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CollectionNode extends Node
{
    /**
     * Creates a new collection node.
     *
     * @param array|\Traversable     $collection     The validated collection
     * @param MetadataInterface      $metadata       The class metadata of that
     *                                               object
     * @param string                 $propertyPath   The property path leading
     *                                               to this node
     * @param string[]               $groups         The groups in which this
     *                                               node should be validated
     * @param string[]|null          $cascadedGroups The groups in which
     *                                               cascaded objects should be
     *                                               validated
     *
     * @throws UnexpectedTypeException If the given value is not an array or
     *                                 an instance of {@link \Traversable}
     */
    public function __construct($collection, MetadataInterface $metadata, $propertyPath, array $groups, $cascadedGroups = null)
    {
        if (!is_array($collection) && !$collection instanceof \Traversable) {
            throw new UnexpectedTypeException($collection, 'object');
        }

        parent::__construct(
            $collection,
            $metadata,
            $propertyPath,
            $groups,
            $cascadedGroups
        );
    }
}
