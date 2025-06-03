<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Extractor;

use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 * Lists available properties using Symfony Serializer Component metadata.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final
 */
class SerializerExtractor implements PropertyListExtractorInterface
{
    public function __construct(
        private readonly ClassMetadataFactoryInterface $classMetadataFactory,
    ) {
    }

    public function getProperties(string $class, array $context = []): ?array
    {
        if (!\array_key_exists('serializer_groups', $context) || (null !== $context['serializer_groups'] && !\is_array($context['serializer_groups']))) {
            return null;
        }

        if (!$this->classMetadataFactory->hasMetadataFor($class)) {
            return null;
        }

        $properties = [];
        $serializerClassMetadata = $this->classMetadataFactory->getMetadataFor($class);

        foreach ($serializerClassMetadata->getAttributesMetadata() as $serializerAttributeMetadata) {
            if (!$serializerAttributeMetadata->isIgnored() && (null === $context['serializer_groups'] || \in_array('*', $context['serializer_groups'], true) || array_intersect($serializerAttributeMetadata->getGroups(), $context['serializer_groups']))) {
                $properties[] = $serializerAttributeMetadata->getName();
            }
        }

        return $properties;
    }
}
