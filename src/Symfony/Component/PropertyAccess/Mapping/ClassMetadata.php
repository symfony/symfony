<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Mapping;

/**
 * @author Luis Ramón López <lrlopez@gmail.com>
 */
class ClassMetadata
{
    /**
     * @var string
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getName()} instead.
     */
    public $name;

    /**
     * @var PropertyMetadata[]
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getPropertyMetadataCollection()} instead.
     */
    public $propertyMetadataCollection = array();

    /**
     * @var \ReflectionClass
     */
    private $reflClass;

    /**
     * Constructs a metadata for the given class.
     *
     * @param string $class
     */
    public function __construct($class)
    {
        $this->name = $class;
    }

    /**
     * Returns the name of the backing PHP class.
     *
     * @return string the name of the backing class
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Adds an {@link AttributeMetadataInterface}.
     *
     * @param PropertyMetadata $propertyMetadata
     */
    public function addPropertyMetadata(PropertyMetadata $propertyMetadata)
    {
        $this->propertyMetadataCollection[$propertyMetadata->getName()] = $propertyMetadata;
    }

    /**
     * Gets the list of {@link PropertyMetadata}.
     *
     * @return PropertyMetadata[]
     */
    public function getPropertyMetadataCollection()
    {
        return $this->propertyMetadataCollection;
    }

    /**
     * Return metadata for a particular property, or null if it doesn't exist.
     *
     * @param string $property
     *
     * @return PropertyMetadata|null
     */
    public function getMetadataForProperty($property)
    {
        return isset($this->propertyMetadataCollection[$property]) ? $this->propertyMetadataCollection[$property] : null;
    }

    /**
     * Merges a {@link ClassMetadata} into the current one.
     *
     * @param self $classMetadata
     */
    public function merge(ClassMetadata $classMetadata)
    {
        foreach ($classMetadata->getPropertyMetadataCollection() as $attributeMetadata) {
            if (isset($this->propertyMetadataCollection[$attributeMetadata->getName()])) {
                $this->propertyMetadataCollection[$attributeMetadata->getName()]->merge($attributeMetadata);
            } else {
                $this->addPropertyMetadata($attributeMetadata);
            }
        }
    }

    /**
     * Returns a {@link \ReflectionClass} instance for this class.
     *
     * @return \ReflectionClass
     */
    public function getReflectionClass()
    {
        if (!$this->reflClass) {
            $this->reflClass = new \ReflectionClass($this->getName());
        }

        return $this->reflClass;
    }

    /**
     * Returns the names of the properties that should be serialized.
     *
     * @return string[]
     */
    public function __sleep()
    {
        return array(
            'name',
            'propertyMetadataCollection',
        );
    }
}
