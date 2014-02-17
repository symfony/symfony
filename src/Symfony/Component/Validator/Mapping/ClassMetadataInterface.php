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

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ClassMetadataInterface extends MetadataInterface, ClassBasedInterface
{
    public function getConstrainedProperties();

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

    public function hasGroupSequence();

    public function getGroupSequence();

    public function isGroupSequenceProvider();
}
