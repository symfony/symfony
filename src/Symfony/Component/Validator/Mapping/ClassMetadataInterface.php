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

use Symfony\Component\Validator\ClassBasedInterface;
use Symfony\Component\Validator\PropertyMetadataContainerInterface as LegacyPropertyMetadataContainerInterface;

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
 * @since  2.5
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see MetadataInterface
 * @see \Symfony\Component\Validator\Constraints\GroupSequence
 * @see \Symfony\Component\Validator\GroupSequenceProviderInterface
 * @see TraversalStrategy
 */
interface ClassMetadataInterface extends MetadataInterface, LegacyPropertyMetadataContainerInterface, ClassBasedInterface
{
    /**
     * Returns the names of all constrained properties.
     *
     * @return string[] A list of property names
     */
    public function getConstrainedProperties();

    /**
     * Returns whether the "Default" group is overridden by a group sequence.
     *
     * If it is, you can access the group sequence with {@link getGroupSequence()}.
     *
     * @return bool Returns true if the "Default" group is overridden
     *
     * @see \Symfony\Component\Validator\Constraints\GroupSequence
     */
    public function hasGroupSequence();

    /**
     * Returns the group sequence that overrides the "Default" group for this
     * class.
     *
     * @return \Symfony\Component\Validator\Constraints\GroupSequence|null The group sequence or null
     *
     * @see \Symfony\Component\Validator\Constraints\GroupSequence
     */
    public function getGroupSequence();

    /**
     * Returns whether the "Default" group is overridden by a dynamic group
     * sequence obtained by the validated objects.
     *
     * If this method returns true, the class must implement
     * {@link \Symfony\Component\Validator\GroupSequenceProviderInterface}.
     * This interface will be used to obtain the group sequence when an object
     * of this class is validated.
     *
     * @return bool Returns true if the "Default" group is overridden by
     *              a dynamic group sequence
     *
     * @see \Symfony\Component\Validator\GroupSequenceProviderInterface
     */
    public function isGroupSequenceProvider();
}
