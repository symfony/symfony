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
use Symfony\Component\Validator\Mapping\TraversalStrategy;

/**
 * A node in the validation graph.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class Node
{
    /**
     * The validated value.
     *
     * @var mixed
     */
    public $value;

    /**
     * The metadata specifying how the value should be validated.
     *
     * @var MetadataInterface|null
     */
    public $metadata;

    /**
     * The property path leading to this node.
     *
     * @var string
     */
    public $propertyPath;

    /**
     * The groups in which the value should be validated.
     *
     * @var string[]
     */
    public $groups;

    /**
     * The groups in which cascaded values should be validated.
     *
     * @var string[]
     */
    public $cascadedGroups;

    /**
     * The strategy used for traversing the validated value.
     *
     * @var integer
     *
     * @see \Symfony\Component\Validator\Mapping\TraversalStrategy
     */
    public $traversalStrategy;

    /**
     * Creates a new property node.
     *
     * @param mixed                  $value          The property value
     * @param MetadataInterface|null $metadata       The property's metadata
     * @param string                 $propertyPath   The property path leading to
     *                                               this node
     * @param string[]               $groups         The groups in which this node
     *                                               should be validated
     * @param string[]|null          $cascadedGroups The groups in which cascaded
     *                                               objects should be validated
     * @param integer                $traversalStrategy
     *
     * @throws UnexpectedTypeException If $cascadedGroups is invalid
     */
    public function __construct($value, MetadataInterface $metadata = null, $propertyPath, array $groups, $cascadedGroups = null, $traversalStrategy = TraversalStrategy::IMPLICIT)
    {
        if (null !== $cascadedGroups && !is_array($cascadedGroups)) {
            throw new UnexpectedTypeException($cascadedGroups, 'null or array');
        }

        $this->value = $value;
        $this->metadata = $metadata;
        $this->propertyPath = $propertyPath;
        $this->groups = $groups;
        $this->cascadedGroups = $cascadedGroups;
        $this->traversalStrategy = $traversalStrategy;
    }
}
