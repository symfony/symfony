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
 * Creates the metadata of a property for a mapping from a source to a target: everything the mapper
 * needs to know about the property that does not depend on the mapped instances.
 *
 * Apart from the Mapping instances it carries, the metadata is made of scalars and arrays so that it can be
 * dumped as a static PHP value.
 *
 * @phpstan-type PropertyMapping array{
 *     mapping: Mapping,
 *     sourceProperty: string,
 *     targetProperty: string,
 *     sourceReadability: PropertyReadability::*,
 *     sourceDeclared: bool,
 *     targetPropertyClass: ?string,
 *     targetWritable: bool,
 * }
 * @phpstan-type PropertyMetadata array{
 *     name: string,
 *     sourceDeclared: bool,
 *     readability: PropertyReadability::*,
 *     mergeReadability: PropertyReadability::*,
 *     mappings: list<PropertyMapping>,
 *     targetHasProperty: bool,
 *     initializedKey: ?string,
 *     sameNameMapping: ?Mapping,
 *     targetPropertyClass: ?string,
 *     targetWritable: bool,
 * }
 *
 * @internal
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
interface PropertyMetadataFactoryInterface
{
    /**
     * @return PropertyMetadata
     */
    public function create(object $source, object $target, string $property): array;
}
