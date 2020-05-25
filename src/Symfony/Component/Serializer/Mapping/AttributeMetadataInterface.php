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
 */
interface AttributeMetadataInterface
{
    /**
     * Gets the attribute name.
     */
    public function getName(): string;

    /**
     * Adds this attribute to the given group.
     */
    public function addGroup(string $group);

    /**
     * Gets groups of this attribute.
     *
     * @return string[]
     */
    public function getGroups(): array;

    /**
     * Sets the serialization max depth for this attribute.
     */
    public function setMaxDepth(?int $maxDepth);

    /**
     * Gets the serialization max depth for this attribute.
     *
     * @return int|null
     */
    public function getMaxDepth();

    /**
     * Sets the serialization name for this attribute.
     */
    public function setSerializedName(string $serializedName = null);

    /**
     * Gets the serialization name for this attribute.
     */
    public function getSerializedName(): ?string;

    /**
     * Sets if this attribute must be ignored or not.
     */
    public function setIgnore(bool $ignore);

    /**
     * Gets if this attribute is ignored or not.
     */
    public function isIgnored(): bool;

    /**
     * Merges an {@see AttributeMetadataInterface} with in the current one.
     */
    public function merge(self $attributeMetadata);
}
