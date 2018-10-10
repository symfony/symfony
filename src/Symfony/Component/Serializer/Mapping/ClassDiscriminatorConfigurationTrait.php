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
trait ClassDiscriminatorConfigurationTrait
{
    private function resolveMappingForMappedObject($object)
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
