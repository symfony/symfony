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
    public function getProperties($class, array $context = array())
    {
        if (!isset($context['serializer_groups']) || !is_array($context['serializer_groups'])) {
            return;
        }

        if (!$this->classMetadataFactory->getMetadataFor($class)) {
            return;
        }

        $properties = array();
        $serializerClassMetadata = $this->classMetadataFactory->getMetadataFor($class);

        foreach ($serializerClassMetadata->getAttributesMetadata() as $serializerAttributeMetadata) {
            if (array_intersect($context['serializer_groups'], $serializerAttributeMetadata->getGroups())) {
                $properties[] = $serializerAttributeMetadata->getName();
            }
        }

        return $properties;
    }
}
