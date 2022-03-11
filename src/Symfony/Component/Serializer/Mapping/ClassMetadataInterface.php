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
 * Stores metadata needed for serializing and deserializing objects of specific class.
 *
 * Primarily, the metadata stores the set of attributes to serialize or deserialize.
 *
 * There may only exist one metadata for each attribute according to its name.
 *
 * @internal
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface ClassMetadataInterface
{
    /**
     * Returns the name of the backing PHP class.
     */
    public function getName(): string;

    /**
     * Adds an {@link AttributeMetadataInterface}.
     */
    public function addAttributeMetadata(AttributeMetadataInterface $attributeMetadata): void;

    /**
     * Gets the list of {@link AttributeMetadataInterface}.
     *
     * @return array<string, AttributeMetadataInterface>
     */
    public function getAttributesMetadata(): array;

    /**
     * Merges a {@link ClassMetadataInterface} in the current one.
     */
    public function merge(self $classMetadata): void;

    /**
     * Returns a {@link \ReflectionClass} instance for this class.
     */
    public function getReflectionClass(): \ReflectionClass;

    public function getClassDiscriminatorMapping(): ?ClassDiscriminatorMapping;

    public function setClassDiscriminatorMapping(?ClassDiscriminatorMapping $mapping): void;
}
