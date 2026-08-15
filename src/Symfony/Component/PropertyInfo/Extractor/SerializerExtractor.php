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

        // Read the key as a literal rather than via AbstractNormalizer::ENABLE_DEFAULT_GROUPS, as
        // PropertyInfo is compatible with older Serializer versions that lack the constant.
        $enableDefaultGroups = $context['enable_default_groups'] ?? false;

        $groups = $context['serializer_groups'] ?? [];
        $defaultGroups = ['Default', (false !== $nsSep = strrpos($class, '\\')) ? substr($class, $nsSep + 1) : $class];

        $groupsHasBeenDefined = $enableDefaultGroups
            ? [] !== $groups
            : null !== ($context['serializer_groups'] ?? null);

        $customGroupsHasBeenDefined = (bool) array_diff($groups, $defaultGroups);

        if ($enableDefaultGroups && !$customGroupsHasBeenDefined) {
            $groups = array_merge($groups, $defaultGroups);
        }

        $properties = [];
        $serializerClassMetadata = $this->classMetadataFactory->getMetadataFor($class);

        foreach ($serializerClassMetadata->getAttributesMetadata() as $serializerAttributeMetadata) {
            if ($serializerAttributeMetadata->isIgnored()) {
                continue;
            }

            if (!($attributeGroups = $serializerAttributeMetadata->getGroups()) && $enableDefaultGroups && !$customGroupsHasBeenDefined) {
                $attributeGroups = $defaultGroups;
            }

            if (!$groupsHasBeenDefined || \in_array('*', $groups, true) || array_intersect($attributeGroups, $groups)) {
                $properties[] = $serializerAttributeMetadata->getName();
            }
        }

        return $properties;
    }
}
