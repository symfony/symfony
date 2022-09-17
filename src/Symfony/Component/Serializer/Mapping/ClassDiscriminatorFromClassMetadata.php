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

use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class ClassDiscriminatorFromClassMetadata implements ClassDiscriminatorResolverInterface
{
    /**
     * @var ClassMetadataFactoryInterface
     */
    private $classMetadataFactory;
    private $mappingForMappedObjectCache = [];

    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory)
    {
        $this->classMetadataFactory = $classMetadataFactory;
    }

    public function getMappingForClass(string $class): ?ClassDiscriminatorMapping
    {
        if ($this->classMetadataFactory->hasMetadataFor($class)) {
            return $this->classMetadataFactory->getMetadataFor($class)->getClassDiscriminatorMapping();
        }

        return null;
    }

    public function getMappingForMappedObject(object|string $object): ?ClassDiscriminatorMapping
    {
        if ($this->classMetadataFactory->hasMetadataFor($object)) {
            $metadata = $this->classMetadataFactory->getMetadataFor($object);

            if (null !== $metadata->getClassDiscriminatorMapping()) {
                return $metadata->getClassDiscriminatorMapping();
            }
        }

        $cacheKey = \is_object($object) ? $object::class : $object;
        if (!\array_key_exists($cacheKey, $this->mappingForMappedObjectCache)) {
            $this->mappingForMappedObjectCache[$cacheKey] = $this->resolveMappingForMappedObject($object);
        }

        return $this->mappingForMappedObjectCache[$cacheKey];
    }

    public function getTypeForMappedObject(object|string $object): ?string
    {
        if (null === $mapping = $this->getMappingForMappedObject($object)) {
            return null;
        }

        return $mapping->getMappedObjectType($object);
    }

    private function resolveMappingForMappedObject(object|string $object)
    {
        $reflectionClass = new \ReflectionClass($object);
        if ($parentClass = $reflectionClass->getParentClass()) {
            return $this->getMappingForMappedObject($parentClass->getName());
        }

        foreach ($reflectionClass->getInterfaceNames() as $interfaceName) {
            if (null !== ($interfaceMapping = $this->getMappingForMappedObject($interfaceName))) {
                return $interfaceMapping;
            }
        }

        return null;
    }
}
