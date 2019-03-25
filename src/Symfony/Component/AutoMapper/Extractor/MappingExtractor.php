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

use Symfony\Component\AutoMapper\Transformer\TransformerFactoryInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 * @internal
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
abstract class MappingExtractor implements MappingExtractorInterface
{
    protected $propertyInfoExtractor;

    protected $transformerFactory;

    protected $accessorExtractor;

    protected $classMetadataFactory;

    public function __construct(PropertyInfoExtractorInterface $propertyInfoExtractor, AccessorExtractorInterface $accessorExtractor, TransformerFactoryInterface $transformerFactory, ClassMetadataFactoryInterface $classMetadataFactory = null)
    {
        $this->propertyInfoExtractor = $propertyInfoExtractor;
        $this->accessorExtractor = $accessorExtractor;
        $this->transformerFactory = $transformerFactory;
        $this->classMetadataFactory = $classMetadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getReadAccessor(string $source, string $target, string $property): ?ReadAccessor
    {
        return $this->accessorExtractor->getReadAccessor($source, $property);
    }

    /**
     * {@inheritdoc}
     */
    public function getWriteMutator(string $source, string $target, string $property): ?WriteMutator
    {
        return $this->accessorExtractor->getWriteMutator($target, $property);
    }

    protected function getMaxDepth($class, $property): ?int
    {
        if ('array' === $class) {
            return null;
        }

        if (null === $this->classMetadataFactory) {
            return null;
        }

        if (!$this->classMetadataFactory->getMetadataFor($class)) {
            return null;
        }

        $serializerClassMetadata = $this->classMetadataFactory->getMetadataFor($class);
        $maxDepth = null;

        foreach ($serializerClassMetadata->getAttributesMetadata() as $serializerAttributeMetadata) {
            if ($serializerAttributeMetadata->getName() === $property) {
                $maxDepth = $serializerAttributeMetadata->getMaxDepth();
            }
        }

        return $maxDepth;
    }

    protected function getGroups($class, $property): ?array
    {
        if ('array' === $class) {
            return null;
        }

        if (null === $this->classMetadataFactory || !$this->classMetadataFactory->getMetadataFor($class)) {
            return null;
        }

        $serializerClassMetadata = $this->classMetadataFactory->getMetadataFor($class);
        $anyGroupFound = false;
        $groups = [];

        foreach ($serializerClassMetadata->getAttributesMetadata() as $serializerAttributeMetadata) {
            $groupsFound = $serializerAttributeMetadata->getGroups();

            if ($groupsFound) {
                $anyGroupFound = true;
            }

            if ($serializerAttributeMetadata->getName() === $property) {
                $groups = $groupsFound;
            }
        }

        if (!$anyGroupFound) {
            return null;
        }

        return $groups;
    }
}
