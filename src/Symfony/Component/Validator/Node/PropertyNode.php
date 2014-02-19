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

use Symfony\Component\Validator\Mapping\PropertyMetadataInterface;

/**
 * Represents the value of a property and its associated metadata.
 *
 * If the property contains an object and should be cascaded, a new
 * {@link ClassNode} instance will be created for that object.
 *
 * Example:
 *
 *     (Article:ClassNode)
 *                \
 *        (author:PropertyNode)
 *                  \
 *            (Author:ClassNode)
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
     * @param mixed                     $value          The property value
     * @param PropertyMetadataInterface $metadata       The property's metadata
     * @param string                    $propertyPath   The property path leading
     *                                                  to this node
     * @param string[]                  $groups         The groups in which this
     *                                                  node should be validated
     * @param string[]                  $cascadedGroups The groups in which
     *                                                  cascaded objects should
     *                                                  be validated
     */
    public function __construct($value, PropertyMetadataInterface $metadata, $propertyPath, array $groups, array $cascadedGroups)
    {
        parent::__construct(
            $value,
            $metadata,
            $propertyPath,
            $groups,
            $cascadedGroups
        );
    }

}
