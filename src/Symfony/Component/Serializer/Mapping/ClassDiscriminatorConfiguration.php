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
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class ClassDiscriminatorConfiguration implements ClassDiscriminatorResolverInterface
{
    use ClassDiscriminatorConfigurationTrait;

    private $mapping;

    /**
     * {@inheritdoc}
     */
    public function getMappingForClass(string $class): ?ClassDiscriminatorMapping
    {
        return $this->mapping[$class] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getMappingForMappedObject($object): ?ClassDiscriminatorMapping
    {
        $class = \is_object($object) ? \get_class($object) : $object;

        return $this->getMappingForClass($class) ?: $this->resolveMappingForMappedObject($object);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeForMappedObject($object): ?string
    {
        if (null === $mapping = $this->getMappingForMappedObject($object)) {
            return null;
        }

        return $mapping->getMappedObjectType($object);
    }

    /**
     * Configure the class discriminator for the given class.
     */
    public function setClassMapping(string $className, ClassDiscriminatorMapping $mapping)
    {
        $this->mapping[$className] = $mapping;
    }
}
