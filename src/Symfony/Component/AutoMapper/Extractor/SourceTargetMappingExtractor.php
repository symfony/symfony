<?php

namespace Symfony\Component\AutoMapper\Extractor;

use Symfony\Component\AutoMapper\MapperMetadataInterface;

/**
 * Extract mapping between two objects, only give properties that have the same name.
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
                if (!$this->propertyInfoExtractor->isWritable($mapperMetadata->getTarget(), $property)) {
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
                    $targetMutator,
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
