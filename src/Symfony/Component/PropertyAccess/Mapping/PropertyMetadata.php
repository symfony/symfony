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
 * Stores metadata needed for overriding properties access methods.
 *
 * @author Luis Ramón López <lrlopez@gmail.com>
 */
class PropertyMetadata
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
     * @var string
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getGetter()} instead.
     */
    public $getter;

    /**
     * @var string
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getSetter()} instead.
     */
    public $setter;

    /**
     * @var string
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getAdder()} instead.
     */
    public $adder;

    /**
     * @var string
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getRemover()} instead.
     */
    public $remover;

    /**
     * Constructs a metadata for the given attribute.
     *
     * @param string $name
     */
    public function __construct($name = null)
    {
        $this->name = $name;
    }

    /**
     * Gets the attribute name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets the setter method name.
     *
     * @return string
     */
    public function getSetter()
    {
        return $this->setter;
    }

    /**
     * Sets the setter method name.
     */
    public function setSetter($setter)
    {
        $this->setter = $setter;
    }

    /**
     * Gets the getter method name.
     *
     * @return string
     */
    public function getGetter()
    {
        return $this->getter;
    }

    /**
     * Sets the getter method name.
     */
    public function setGetter($getter)
    {
        $this->getter = $getter;
    }

    /**
     * Gets the adder method name.
     *
     * @return string
     */
    public function getAdder()
    {
        return $this->adder;
    }

    /**
     * Sets the adder method name.
     */
    public function setAdder($adder)
    {
        $this->adder = $adder;
    }

    /**
     * Gets the remover method name.
     *
     * @return string
     */
    public function getRemover()
    {
        return $this->remover;
    }

    /**
     * Sets the remover method name.
     */
    public function setRemover($remover)
    {
        $this->remover = $remover;
    }

    /**
     * Merges another PropertyMetadata with the current one.
     *
     * @param self $propertyMetadata
     */
    public function merge(PropertyMetadata $propertyMetadata)
    {
        // Overwrite only if not defined
        if (null === $this->getter) {
            $this->getter = $propertyMetadata->getter;
        }
        if (null === $this->setter) {
            $this->setter = $propertyMetadata->setter;
        }
        if (null === $this->adder) {
            $this->adder = $propertyMetadata->adder;
        }
        if (null === $this->remover) {
            $this->remover = $propertyMetadata->remover;
        }
    }

    /**
     * Returns the names of the properties that should be serialized.
     *
     * @return string[]
     */
    public function __sleep()
    {
        return array('name', 'getter', 'setter', 'adder', 'remover');
    }
}
