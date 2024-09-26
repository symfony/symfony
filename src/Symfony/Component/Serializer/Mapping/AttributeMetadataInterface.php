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

use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * Stores metadata needed for serializing and deserializing attributes.
 *
 * Primarily, the metadata stores serialization groups.
 *
 * @internal
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @method string[]       getSerializedNames() Gets all the serialized names per group ("*" being the base name applied to all groups).
 * @method PropertyPath[] getSerializedPaths() Gets all the serialized paths per group ("*" being the base path applied to all groups).
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
    public function addGroup(string $group): void;

    /**
     * Gets groups of this attribute.
     *
     * @return string[]
     */
    public function getGroups(): array;

    /**
     * Sets the serialization max depth for this attribute.
     */
    public function setMaxDepth(?int $maxDepth): void;

    /**
     * Gets the serialization max depth for this attribute.
     */
    public function getMaxDepth(): ?int;

    /**
     * Sets the serialization name for this attribute.
     *
     * @param string[] $groups
     */
    public function setSerializedName(?string $serializedName /* , array $groups = ['*'] */): void;

    /**
     * Gets the serialization name for this attribute.
     *
     * @param string[] $groups
     */
    public function getSerializedName(/* array $groups = ['*'] */): ?string;

    /**
     * Sets the serialization path for this attribute.
     *
     * @param string[] $groups
     */
    public function setSerializedPath(?PropertyPath $serializedPath /* , array $groups = ['*'] */): void;

    /**
     * Gets the serialization path for this attribute.
     *
     * @param string[] $groups
     */
    public function getSerializedPath(/* array $groups = ['*'] */): ?PropertyPath;

    /**
     * Sets if this attribute must be ignored or not.
     */
    public function setIgnore(bool $ignore): void;

    /**
     * Gets if this attribute is ignored or not.
     */
    public function isIgnored(): bool;

    /**
     * Merges an {@see AttributeMetadataInterface} with in the current one.
     */
    public function merge(self $attributeMetadata): void;

    /**
     * Gets all the normalization contexts per group ("*" being the base context applied to all groups).
     */
    public function getNormalizationContexts(): array;

    /**
     * Gets the computed normalization contexts for given groups.
     */
    public function getNormalizationContextForGroups(array $groups): array;

    /**
     * Sets the normalization context for given groups.
     */
    public function setNormalizationContextForGroups(array $context, array $groups = []): void;

    /**
     * Gets all the denormalization contexts per group ("*" being the base context applied to all groups).
     */
    public function getDenormalizationContexts(): array;

    /**
     * Gets the computed denormalization contexts for given groups.
     */
    public function getDenormalizationContextForGroups(array $groups): array;

    /**
     * Sets the denormalization context for given groups.
     */
    public function setDenormalizationContextForGroups(array $context, array $groups = []): void;
}
