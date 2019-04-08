<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Extractor;

use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 * Filter properties given a specific set of groups
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
class GroupPropertyListExtractor implements PropertyListExtractorInterface
{
    public const GROUPS = 'groups';

    private $classMetadataFactory;

    private $extractor;

    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory, PropertyListExtractorInterface $extractor = null)
    {
        $this->extractor = $extractor;
        $this->classMetadataFactory = $classMetadataFactory;
    }

    public function getProperties($class, array $context = [])
    {
        $properties = null;

        if (null !== $this->extractor) {
            $properties = $this->extractor->getProperties($class, $context);

            if (null === $properties) {
                return null;
            }
        }

        $groups = $context[self::GROUPS] ?? null;
        $groups = (\is_array($groups) || is_scalar($groups)) ? (array) $groups : false;
        $groupProperties = [];

        foreach ($this->classMetadataFactory->getMetadataFor($class)->getAttributesMetadata() as $attributeMetadata) {
            $name = $attributeMetadata->getName();

            if (false === $groups || array_intersect($attributeMetadata->getGroups(), $groups)) {
                $groupProperties[] = $name;
            }
        }

        if (null === $properties) {
            return $groupProperties;
        }

        return array_intersect($properties, $groupProperties);
    }
}
