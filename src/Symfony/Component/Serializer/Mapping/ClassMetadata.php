<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Mapping;

/**
 * Stores all metadata needed for serializing objects of specific class.
 *
 * Primarily, the metadata stores serialization groups.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ClassMetadata
{
    /**
     * @var string
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getClassName()} instead.
     */
    public $name;

    /**
     * @var array
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getGroups()} instead.
     */
    public $attributesGroups = array();

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
     * @return string The name of the backing class.
     */
    public function getClassName()
    {
        return $this->name;
    }

    /**
     * Gets serialization groups.
     *
     * @return array
     */
    public function getAttributesGroups()
    {
        return $this->attributesGroups;
    }

    /**
     * Adds an attribute to a serialization group
     *
     * @param string $attribute
     * @param string $group
     * @throws \InvalidArgumentException
     */
    public function addAttributeGroup($attribute, $group)
    {
        if (!is_string($attribute) || !is_string($group)) {
            throw new \InvalidArgumentException('Arguments must be strings.');
        }

        if (!isset($this->groups[$group]) || !in_array($attribute, $this->attributesGroups[$group])) {
            $this->attributesGroups[$group][] = $attribute;
        }
    }

    /**
     * Merges attributes' groups.
     *
     * @param ClassMetadata $classMetadata
     */
    public function mergeAttributesGroups(ClassMetadata $classMetadata)
    {
        foreach ($classMetadata->getAttributesGroups() as $group => $attributes) {
            foreach ($attributes as $attribute) {
                $this->addAttributeGroup($attribute, $group);
            }
        }
    }

    /**
     * Returns a ReflectionClass instance for this class.
     *
     * @return \ReflectionClass
     */
    public function getReflectionClass()
    {
        if (!$this->reflClass) {
            $this->reflClass = new \ReflectionClass($this->getClassName());
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
            'attributesGroups',
        );
    }
}
