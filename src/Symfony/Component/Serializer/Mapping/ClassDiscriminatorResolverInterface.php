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
 * Knows how to get the class discriminator mapping for classes and objects.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
interface ClassDiscriminatorResolverInterface
{
    public function getMappingForClass(string $class): ?ClassDiscriminatorMapping;

    public function getMappingForMappedObject(object|string $object): ?ClassDiscriminatorMapping;

    public function getTypeForMappedObject(object|string $object): ?string;
}
