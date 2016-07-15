<?php

namespace Symfony\Component\Serializer\PropertyManager;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 *
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class MetadataAwarePropertyTypeExtractor implements PropertyTypeExtractorInterface
{
    /**
     * @var ClassMetadataFactoryInterface
     */
    private $classMetadataFactory;

    /**
     * @var PropertyTypeExtractorInterface
     */
    private $propertyTypeExtractor;

    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory, PropertyTypeExtractorInterface $propertyTypeExtractor = null)
    {
        $this->classMetadataFactory  = $classMetadataFactory;
        $this->propertyTypeExtractor  = $propertyTypeExtractor;
    }

    public function getTypes($class, $property, array $context = array())
    {
        /** @var ClassMetadataInterface $classMetadata */
        $classMetadata = $this->classMetadataFactory->getMetadataFor($class);
        $attributeMetadata = $classMetadata->getAttributesMetadata();

        if (null !== $string = $attributeMetadata[$property]->getType()) {
            return array($this->convertStringToType($string));
        }


        if (null === $this->propertyTypeExtractor || null === $types = $this->propertyTypeExtractor->getTypes($class, $property)) {
            return null;
        }

        return $types;
    }

    /**
     * Convert a plain string to a Type
     *
     * @param string $doctype
     */
    private function convertStringToType($docType)
    {
        if ($collection = '[]' === substr($docType, -2)) {
            $docType = substr($docType, 0, -2);
        }

        list($phpType, $class) = $this->getPhpTypeAndClass($docType);
        $array = 'array' === $docType;

        if ($collection || $array) {
            if ($array || 'mixed' === $docType) {
                $collectionKeyType = null;
                $collectionValueType = null;
            } else {
                $collectionKeyType = new Type(Type::BUILTIN_TYPE_INT);
                $collectionValueType = new Type($phpType, false, $class);
            }

            return new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, $collectionKeyType, $collectionValueType);
        }

        return new Type($phpType, true, $class);
    }

    /**
     * Gets an array containing the PHP type and the class.
     *
     * @param string $docType
     *
     * @return array
     */
    private function getPhpTypeAndClass($docType)
    {
        if (in_array($docType, Type::$builtinTypes)) {
            return array($docType, null);
        }

        return array('object', substr($docType, 1));
    }
}