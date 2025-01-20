<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Mapping;

use Symfony\Component\JsonEncoder\Exception\RuntimeException;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Loads basic properties encoding/decoding metadata for a given $className.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class PropertyMetadataLoader implements PropertyMetadataLoaderInterface
{
    public function __construct(
        private TypeResolverInterface $typeResolver,
    ) {
    }

    public function load(string $className, array $options = [], array $context = []): array
    {
        $result = [];

        try {
            $classReflection = new \ReflectionClass($className);
        } catch (\ReflectionException $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        foreach ($classReflection->getProperties() as $reflectionProperty) {
            if (!$reflectionProperty->isPublic()) {
                continue;
            }

            $name = $encodedName = $reflectionProperty->getName();
            $type = $this->typeResolver->resolve($reflectionProperty);

            $result[$encodedName] = new PropertyMetadata($name, $type);
        }

        return $result;
    }
}
