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

use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Mapping\TraversalStrategy;

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
     * @param array|\Traversable $collection         The validated collection
     * @param string             $propertyPath       The property path leading
     *                                               to this node
     * @param string[]           $groups             The groups in which this
     *                                               node should be validated
     * @param string[]|null      $cascadedGroups     The groups in which
     *                                               cascaded objects should be
     *                                               validated
     * @param integer            $traversalStrategy  The traversal strategy
     *
     * @throws \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function __construct($collection, $propertyPath, array $groups, $cascadedGroups = null, $traversalStrategy = TraversalStrategy::TRAVERSE)
    {
        if (!is_array($collection) && !$collection instanceof \Traversable) {
            throw new ConstraintDefinitionException(sprintf(
                'Traversal was enabled for "%s", but this class '.
                'does not implement "\Traversable".',
                get_class($collection)
            ));
        }

        parent::__construct(
            $collection,
            null,
            $propertyPath,
            $groups,
            $cascadedGroups,
            $traversalStrategy
        );
    }
}
