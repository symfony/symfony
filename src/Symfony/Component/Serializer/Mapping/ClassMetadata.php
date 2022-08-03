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
 * {@inheritdoc}
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ClassMetadata implements ClassMetadataInterface
{
    /**
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getName()} instead.
     */
    public $name;

    /**
     * @var AttributeMetadataInterface[]
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getAttributesMetadata()} instead.
     */
    public $attributesMetadata = [];

    /**
     * @var \ReflectionClass
     */
    private $reflClass;

    /**
     * @var ClassDiscriminatorMapping|null
     *
     * @internal This property is public in order to reduce the size of the
     *           class' serialized representation. Do not access it. Use
     *           {@link getClassDiscriminatorMapping()} instead.
     */
    public $classDiscriminatorMapping;

    /**
     * Constructs a metadata for the given class.
     */
    public function __construct(string $class, ClassDiscriminatorMapping $classDiscriminatorMapping = null)
    {
        $this->name = $class;
        $this->classDiscriminatorMapping = $classDiscriminatorMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function addAttributeMetadata(AttributeMetadataInterface $attributeMetadata)
    {
        $this->attributesMetadata[$attributeMetadata->getName()] = $attributeMetadata;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributesMetadata(): array
    {
        return $this->attributesMetadata;
    }

    /**
     * {@inheritdoc}
     */
    public function merge(ClassMetadataInterface $classMetadata)
    {
        foreach ($classMetadata->getAttributesMetadata() as $attributeMetadata) {
            if (isset($this->attributesMetadata[$attributeMetadata->getName()])) {
                $this->attributesMetadata[$attributeMetadata->getName()]->merge($attributeMetadata);
            } else {
                $this->addAttributeMetadata($attributeMetadata);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getReflectionClass(): \ReflectionClass
    {
        if (!$this->reflClass) {
            $this->reflClass = new \ReflectionClass($this->getName());
        }

        return $this->reflClass;
    }

    /**
     * {@inheritdoc}
     */
    public function getClassDiscriminatorMapping(): ?ClassDiscriminatorMapping
    {
        return $this->classDiscriminatorMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function setClassDiscriminatorMapping(ClassDiscriminatorMapping $mapping = null)
    {
        $this->classDiscriminatorMapping = $mapping;
    }

    /**
     * Returns the names of the properties that should be serialized.
     *
     * @return string[]
     */
    public function __sleep(): array
    {
        return [
            'name',
            'attributesMetadata',
            'classDiscriminatorMapping',
        ];
    }
}
