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

use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\GroupSequenceProviderInterface;

/**
 * Stores all metadata needed for validating objects of specific class.
 *
 * Most importantly, the metadata stores the constraints against which an object
 * and its properties should be validated.
 *
 * Additionally, the metadata stores whether the "Default" group is overridden
 * by a group sequence for that class and whether instances of that class
 * should be traversed or not.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see MetadataInterface
 * @see GroupSequence
 * @see GroupSequenceProviderInterface
 * @see TraversalStrategy
 */
interface ClassMetadataInterface extends MetadataInterface
{
    /**
     * Returns the names of all constrained properties.
     *
     * @return string[]
     */
    public function getConstrainedProperties();

    /**
     * Returns whether the "Default" group is overridden by a group sequence.
     *
     * If it is, you can access the group sequence with {@link getGroupSequence()}.
     *
     * @return bool
     */
    public function hasGroupSequence();

    /**
     * Returns the group sequence that overrides the "Default" group for this
     * class.
     *
     * @return GroupSequence|null
     */
    public function getGroupSequence();

    /**
     * Returns whether the "Default" group is overridden by a dynamic group
     * sequence obtained by the validated objects.
     *
     * If this method returns true, the class must implement
     * {@link GroupSequenceProviderInterface}.
     * This interface will be used to obtain the group sequence when an object
     * of this class is validated.
     *
     * @return bool
     */
    public function isGroupSequenceProvider();

    /**
     * Check if there's any metadata attached to the given named property.
     *
     * @param string $property The property name
     *
     * @return bool
     */
    public function hasPropertyMetadata(string $property);

    /**
     * Returns all metadata instances for the given named property.
     *
     * If your implementation does not support properties, throw an exception
     * in this method (for example a <tt>BadMethodCallException</tt>).
     *
     * @param string $property The property name
     *
     * @return PropertyMetadataInterface[]
     */
    public function getPropertyMetadata(string $property);

    /**
     * Returns the name of the backing PHP class.
     *
     * @return string
     */
    public function getClassName();
}
