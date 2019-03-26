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
 * Extracts mapping between two objects, only gives properties that have the same name.
 *
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
class SourceTargetMappingExtractor extends MappingExtractor
{
    /**
     * {@inheritdoc}
     */
    public function getPropertiesMapping(MapperMetadataInterface $mapperMetadata): array
    {
        $sourceProperties = array_unique($this->propertyInfoExtractor->getProperties($mapperMetadata->getSource()));
        $targetProperties = array_unique($this->propertyInfoExtractor->getProperties($mapperMetadata->getTarget()));

        if (null === $sourceProperties || null === $targetProperties) {
            return [];
        }

        $mapping = [];

        foreach ($sourceProperties as $property) {
            if (!$this->propertyInfoExtractor->isReadable($mapperMetadata->getSource(), $property)) {
                continue;
            }

            if (\in_array($property, $targetProperties, true)) {
                $targetMutatorConstruct = $this->accessorExtractor->getWriteMutator($mapperMetadata->getTarget(), $property, true);

                if ((null === $targetMutatorConstruct || null === $targetMutatorConstruct->getParameter()) && !$this->propertyInfoExtractor->isWritable($mapperMetadata->getTarget(), $property)) {
                    continue;
                }

                $sourceTypes = $this->propertyInfoExtractor->getTypes($mapperMetadata->getSource(), $property);
                $targetTypes = $this->propertyInfoExtractor->getTypes($mapperMetadata->getTarget(), $property);
                $transformer = $this->transformerFactory->getTransformer($sourceTypes, $targetTypes, $mapperMetadata);

                if (null === $transformer) {
                    continue;
                }

                $sourceAccessor = $this->accessorExtractor->getReadAccessor($mapperMetadata->getSource(), $property);
                $targetMutator = $this->accessorExtractor->getWriteMutator($mapperMetadata->getTarget(), $property, false);

                $maxDepthSource = $this->getMaxDepth($mapperMetadata->getSource(), $property);
                $maxDepthTarget = $this->getMaxDepth($mapperMetadata->getTarget(), $property);
                $maxDepth = null;

                if (null !== $maxDepthSource && null !== $maxDepthTarget) {
                    $maxDepth = min($maxDepthSource, $maxDepthTarget);
                } elseif (null !== $maxDepthSource) {
                    $maxDepth = $maxDepthSource;
                } elseif (null !== $maxDepthTarget) {
                    $maxDepth = $maxDepthTarget;
                }

                $mapping[] = new PropertyMapping(
                    $sourceAccessor,
                    $targetMutator ?? $targetMutatorConstruct,
                    $transformer,
                    $property,
                    false,
                    $this->getGroups($mapperMetadata->getSource(), $property),
                    $this->getGroups($mapperMetadata->getTarget(), $property),
                    $maxDepth
                );
            }
        }

        return $mapping;
    }
}
