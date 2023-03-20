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

use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Loads basic properties encoding/decoding metadata for a given $className.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final readonly class PropertyMetadataLoader implements PropertyMetadataLoaderInterface
{
    public function __construct(
        private TypeResolverInterface $typeResolver,
    ) {
    }

    public function load(string $className, array $config, array $context): array
    {
        $result = [];

        foreach ((new \ReflectionClass($className))->getProperties() as $reflectionProperty) {
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
