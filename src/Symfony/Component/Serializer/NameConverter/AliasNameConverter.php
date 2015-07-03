<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\NameConverter;

use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 * Converts name of properties using the alias specified in the attribute metadata.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AliasNameConverter implements NameConverterInterface
{
    /**
     * @var ClassMetadataFactoryInterface
     */
    private $classMetadataFactory;

    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory)
    {
        $this->classMetadataFactory = $classMetadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $propertyName)
    {
        $attributesMetadata = $this->getAttributesMetadata($object);

        if (isset($attributesMetadata[$propertyName]) && $alias = $attributesMetadata[$propertyName]->getAlias()) {
            return $alias;
        }

        return $propertyName;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($object, $propertyName)
    {
        $attributesMetadata = $this->getAttributesMetadata($object);

        // TODO: cache that
        foreach ($attributesMetadata as $attributeMetadata) {
            $alias = $attributeMetadata->getAlias();
            if ($propertyName === $alias) {
                return $alias;
            }
        }

        return $propertyName;
    }

    /**
     * Gets attributes metadata.
     *
     * @param string|object $class
     *
     * @return array
     */
    private function getAttributesMetadata($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        return $this->classMetadataFactory->getMetadataFor($class)->getAttributesMetadata();
    }
}
