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

/**
 * Represents an object and its class metadata in the validation graph.
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
     * @param object                 $object         The validated object
     * @param ClassMetadataInterface $metadata       The class metadata of that
     *                                               object
     * @param string                 $propertyPath   The property path leading
     *                                               to this node
     * @param string[]               $groups         The groups in which this
     *                                               node should be validated
     * @param string[]|null          $cascadedGroups The groups in which
     *                                               cascaded objects should be
     *                                               validated
     *
     * @throws UnexpectedTypeException If the given value is not an object
     */
    public function __construct($object, ClassMetadataInterface $metadata, $propertyPath, array $groups, $cascadedGroups = null)
    {
        if (!is_object($object)) {
            throw new UnexpectedTypeException($object, 'object');
        }

        parent::__construct(
            $object,
            $metadata,
            $propertyPath,
            $groups,
            $cascadedGroups
        );
    }
}
