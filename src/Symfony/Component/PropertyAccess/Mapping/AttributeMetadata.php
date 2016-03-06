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
 * {@inheritdoc}
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class AttributeMetadata implements AttributeMetadataInterface
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
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getSetter()
    {
        return $this->setter;
    }

    /**
     * {@inheritdoc}
     */
    public function setSetter($setter)
    {
        $this->setter = $setter;
    }

    /**
     * {@inheritdoc}
     */
    public function getGetter()
    {
        return $this->getter;
    }

    /**
     * {@inheritdoc}
     */
    public function setGetter($getter)
    {
        $this->getter = $getter;
    }

    /**
     * {@inheritdoc}
     */
    public function getAdder()
    {
        return $this->adder;
    }

    /**
     * {@inheritdoc}
     */
    public function setAdder($adder)
    {
        $this->adder = $adder;
    }

    /**
     * {@inheritdoc}
     */
    public function getRemover()
    {
        return $this->remover;
    }

    /**
     * {@inheritdoc}
     */
    public function setRemover($remover)
    {
        $this->remover = $remover;
    }

    /**
     * {@inheritdoc}
     */
    public function merge(AttributeMetadataInterface $attributeMetadata)
    {
        // Overwrite only if not defined
        if (null === $this->getter) {
            $this->getter = $attributeMetadata->getGetter();
        }
        if (null === $this->setter) {
            $this->setter = $attributeMetadata->getSetter();
        }
        if (null === $this->adder) {
            $this->adder = $attributeMetadata->getAdder();
        }
        if (null === $this->remover) {
            $this->remover = $attributeMetadata->getRemover();
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
