<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

/**
 * A container for validation metadata of a property.
 *
 * What exactly you define as "property" is up to you. The validator expects
 * implementations of {@link MetadataInterface} that contain constraints and
 * optionally a list of named properties that also have constraints (and may
 * have further sub properties). Such properties are mapped by implementations
 * of this interface.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see MetadataInterface
 *
 * @deprecated since version 2.5, to be removed in 3.0.
 *             Use {@link Mapping\PropertyMetadataInterface} instead.
 */
interface PropertyMetadataInterface extends MetadataInterface
{
    /**
     * Returns the name of the property.
     *
     * @return string The property name.
     */
    public function getPropertyName();

    /**
     * Extracts the value of the property from the given container.
     *
     * @param mixed $containingValue The container to extract the property value from.
     *
     * @return mixed The value of the property.
     */
    public function getPropertyValue($containingValue);
}
