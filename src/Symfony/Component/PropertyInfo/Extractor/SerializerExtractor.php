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
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * Lists available properties using Symfony Serializer Component metadata.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final
 */
class SerializerExtractor implements PropertyListExtractorInterface
{
    private $classMetadataFactory;

    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory)
    {
        $this->classMetadataFactory = $classMetadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties(string $class, array $context = []): ?array
    {
        if (!\array_key_exists('serializer_groups', $context) || (null !== $context['serializer_groups'] && !\is_array($context['serializer_groups']))) {
            return null;
        }

        if (!$this->classMetadataFactory->getMetadataFor($class)) {
            return null;
        }

        $properties = [];
        $serializerClassMetadata = $this->classMetadataFactory->getMetadataFor($class);

        foreach ($serializerClassMetadata->getAttributesMetadata() as $serializerAttributeMetadata) {
            $ignored = method_exists($serializerAttributeMetadata, 'isIgnored') && $serializerAttributeMetadata->isIgnored();
            if (!$ignored && (null === $context['serializer_groups'] || array_intersect($context['serializer_groups'], $this->getAttributeGroups($serializerAttributeMetadata)))) {
                $properties[] = $serializerAttributeMetadata->getName();
            }
        }

        return $properties;
    }

    private function getAttributeGroups(AttributeMetadataInterface $serializerAttributeMetadata): array
    {
        $groups = empty($serializerAttributeMetadata->getGroups()) ? [AbstractNormalizer::DEFAULT_GROUP_FOR_ATTRIBUTE_WITHOUT_GROUPS] : $serializerAttributeMetadata->getGroups();

        return array_merge($groups, ['*']);
    }
}
