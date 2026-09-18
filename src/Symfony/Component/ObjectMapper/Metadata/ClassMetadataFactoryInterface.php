<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Metadata;

/**
 * Creates the metadata of a mapping from a source to a target: everything the mapper needs to
 * know about the pair that does not depend on the mapped instances.
 *
 * Apart from the Mapping instances it carries, the metadata is made of scalars and arrays so that it can be
 * dumped as a static PHP value.
 *
 * @phpstan-type ConstructorParameterMetadata array{hasDefault: bool, default: mixed, readOnly: bool}
 * @phpstan-type ClassMetadata array{
 *     readMetadataFromTarget: bool,
 *     classMappings: list<Mapping>,
 *     classMappingsFromTarget: list<Mapping>,
 *     hasConstructor: bool,
 *     constructorParameters: array<string, ConstructorParameterMetadata>,
 *     requiredConstructorParameters: int,
 * }
 *
 * @internal
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
interface ClassMetadataFactoryInterface
{
    /**
     * @return ClassMetadata
     */
    public function create(object $source, object $target): array;
}
