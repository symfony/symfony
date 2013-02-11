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
 * A container for {@link PropertyMetadataInterface} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface PropertyMetadataContainerInterface
{
    /**
     * Check if there's any metadata attached to the given named property.
     *
     * @param string $property The property name.
     *
     * @return Boolean
     */
    public function hasPropertyMetadata($property);

    /**
     * Returns all metadata instances for the given named property.
     *
     * If your implementation does not support properties, simply throw an
     * exception in this method (for example a <tt>BadMethodCallException</tt>).
     *
     * @param string $property The property name.
     *
     * @return PropertyMetadataInterface[] A list of metadata instances. Empty if
     *                                     no metadata exists for the property.
     */
    public function getPropertyMetadata($property);
}
