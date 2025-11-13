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
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final since Symfony 7.4
 */
class ClassMetadata implements ClassMetadataInterface
{
    private string $name;

    /**
     * @var AttributeMetadataInterface[]
     */
    private array $attributesMetadata = [];

    private ?\ReflectionClass $reflClass = null;
    private ?ClassDiscriminatorMapping $classDiscriminatorMapping = null;

    public function __construct(string $class, ?ClassDiscriminatorMapping $classDiscriminatorMapping = null)
    {
        $this->name = $class;
        $this->classDiscriminatorMapping = $classDiscriminatorMapping;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function addAttributeMetadata(AttributeMetadataInterface $attributeMetadata): void
    {
        $this->attributesMetadata[$attributeMetadata->getName()] = $attributeMetadata;
    }

    public function getAttributesMetadata(): array
    {
        return $this->attributesMetadata;
    }

    public function merge(ClassMetadataInterface $classMetadata): void
    {
        foreach ($classMetadata->getAttributesMetadata() as $attributeMetadata) {
            if (isset($this->attributesMetadata[$attributeMetadata->getName()])) {
                $this->attributesMetadata[$attributeMetadata->getName()]->merge($attributeMetadata);
            } else {
                $this->addAttributeMetadata($attributeMetadata);
            }
        }
    }

    public function getReflectionClass(): \ReflectionClass
    {
        return $this->reflClass ??= new \ReflectionClass($this->getName());
    }

    public function getClassDiscriminatorMapping(): ?ClassDiscriminatorMapping
    {
        return $this->classDiscriminatorMapping;
    }

    public function setClassDiscriminatorMapping(?ClassDiscriminatorMapping $mapping): void
    {
        $this->classDiscriminatorMapping = $mapping;
    }

    /**
     * @internal since Symfony 7.4, will be replaced by `__serialize()` in 8.0
     *
     * @final since Symfony 7.4, will be replaced by `__serialize()` in 8.0
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
