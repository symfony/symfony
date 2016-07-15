<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Mapping;

/**
 * Stores metadata needed for serializing and deserializing attributes.
 *
 * Primarily, the metadata stores serialization groups.
 *
 * @internal
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
interface AttributeMetadataInterface
{
    /**
     * Gets the attribute name.
     *
     * @return string
     */
    public function getName();

    /**
     * Get the getter for this attribute.
     *
     * @return string|null
     */
    public function getAccessorGetter();

    /**
     * Set the getter for this attribute.
     *
     * @param string|null
     */
    public function setAccessorGetter($function);

    /**
     * Get the setter for this attribute.
     *
     * @return string|null
     */
    public function getAccessorSetter();

    /**
     * Set the setter for this attribute.
     *
     * @param string|null
     */
    public function setAccessorSetter($function);

    /**
     * True if this attribute should be excluded.
     *
     * @return boolean|null
     */
    public function getExclude();

    /**
     * Set boolean value if this attribute should be excluded.
     *
     * @param bool $bool
     */
    public function setExclude($bool);

    /**
     * True if this attribute should be exposed.
     *
     * @return boolean|null
     */
    public function getExpose();

    /**
     * Set boolean value if this attribute should be exposed.
     *
     * @param bool $bool
     */
    public function setExpose($bool);

    /**
     * Adds this attribute to the given group.
     *
     * @param string $group
     */
    public function addGroup($group);

    /**
     * Gets groups of this attribute.
     *
     * @return string[]
     */
    public function getGroups();

    /**
     * Sets the serialization max depth for this attribute.
     *
     * @param int|null $maxDepth
     */
    public function setMaxDepth($maxDepth);

    /**
     * Gets the serialization max depth for this attribute.
     *
     * @return int|null
     */
    public function getMaxDepth();

    /**
     * True if this attribute should be ignored when deserializing.
     *
     * @return boolean|null
     */
    public function getReadOnly();

    /**
     * Set boolean value if this is a read only attribute.
     *
     * @param bool $bool
     */
    public function setReadOnly($bool);

    /**
     * Gets the serialized name of this attribute.
     *
     * @return string|null
     */
    public function getSerializedName();

    /**
     * Set the name of this property after serialization.
     *
     * @param string $name
     */
    public function setSerializedName($name);

    /**
     * Gets the type (ie FQCN) of the attribute value.
     *
     * @return string|null
     */
    public function getType();

    /**
     * Set the type of this attribute's value. A type could be Fully Qualified Class Name.
     *
     * @param string $fqcn
     */
    public function setType($fqcn);

    /**
     * Merges an {@see AttributeMetadataInterface} with in the current one.
     */
    public function merge(self $attributeMetadata);
}
