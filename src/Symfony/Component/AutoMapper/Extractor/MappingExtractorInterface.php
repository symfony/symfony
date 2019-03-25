<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Extractor;

use Symfony\Component\AutoMapper\MapperMetadataInterface;

/**
 * Extracts mapping.
 *
 * @internal
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
interface MappingExtractorInterface
{
    /**
     * Extracts properties mapped for a given source and target.
     *
     * @return PropertyMapping[]
     */
    public function getPropertiesMapping(MapperMetadataInterface $mapperMetadata): array;

    /**
     * Extracts read accessor for a given source, target and property.
     */
    public function getReadAccessor(string $source, string $target, string $property): ?ReadAccessor;

    /**
     * Extracts write mutator for a given source, target and property.
     */
    public function getWriteMutator(string $source, string $target, string $property): ?WriteMutator;
}
