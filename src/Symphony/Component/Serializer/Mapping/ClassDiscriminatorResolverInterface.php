<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Mapping;

/**
 * Knows how to get the class discriminator mapping for classes and objects.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
interface ClassDiscriminatorResolverInterface
{
    /**
     * @param string $class
     *
     * @return ClassDiscriminatorMapping|null
     */
    public function getMappingForClass(string $class): ?ClassDiscriminatorMapping;

    /**
     * @param object|string $object
     *
     * @return ClassDiscriminatorMapping|null
     */
    public function getMappingForMappedObject($object): ?ClassDiscriminatorMapping;

    /**
     * @param object|string $object
     *
     * @return string|null
     */
    public function getTypeForMappedObject($object): ?string;
}
