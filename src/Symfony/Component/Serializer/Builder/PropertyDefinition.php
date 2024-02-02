<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Builder;

/**
 * All information about a specific property to be able to build a good Normalizer.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @internal
 *
 * @experimental in 7.1
 */
class PropertyDefinition
{
    private string $propertyName;
    private ?string $normalizedName = null;
    private ?string $getterName = null;
    private ?string $setterName = null;
    private bool $isCollection = false;
    private ?int $constructorArgument = null;
    private mixed $constructorDefaultValue = null;
    private bool $hasConstructorDefaultValue = false;
    private bool $isReadable = false;
    private bool $isWriteable = false;

    /**
     * Ie, other classes.
     *
     * @var string[]
     */
    private array $nonPrimitiveTypes = [];

    /**
     * string, int, float, bool, null.
     *
     * @var string[]
     */
    private array $scalarTypes = [];

    public function __construct(string $propertyName)
    {
        $this->propertyName = $propertyName;
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    public function isConstructorArgument(): bool
    {
        return null !== $this->constructorArgument;
    }

    public function getConstructorArgumentOrder(): ?int
    {
        return $this->constructorArgument;
    }

    /**
     * First argument is 0, next argument is 1 etc..
     */
    public function setConstructorArgumentOrder(int $constructorArgument): void
    {
        $this->constructorArgument = $constructorArgument;
    }

    public function setIsReadable(bool $isReadable): void
    {
        $this->isReadable = $isReadable;
    }

    public function setIsWriteable(bool $isWriteable): void
    {
        $this->isWriteable = $isWriteable;
    }

    public function setIsCollection(bool $isCollection): void
    {
        $this->isCollection = $isCollection;
    }

    public function setNonPrimitiveTypes(array $nonPrimitiveTypes): void
    {
        $this->nonPrimitiveTypes = $nonPrimitiveTypes;
    }

    public function setGetterName(?string $getterName): void
    {
        $this->getterName = $getterName;
    }

    public function setSetterName(?string $setterName): void
    {
        $this->setterName = $setterName;
    }

    public function isReadable(): bool
    {
        return $this->isReadable;
    }

    public function isWriteable(): bool
    {
        return $this->isWriteable || $this->isConstructorArgument();
    }

    public function getGetterName(): ?string
    {
        return $this->getterName;
    }

    public function getSetterName(): ?string
    {
        return $this->setterName;
    }

    public function isCollection(): bool
    {
        return $this->isCollection;
    }

    public function getNormalizedName(): string
    {
        return $this->normalizedName ?? $this->propertyName;
    }

    /**
     * @return string[]
     */
    public function getNonPrimitiveTypes(): array
    {
        return $this->nonPrimitiveTypes;
    }

    public function hasNoTypeDefinition(): bool
    {
        return [] === $this->nonPrimitiveTypes && [] === $this->scalarTypes;
    }

    public function isOnlyScalarTypes(): ?bool
    {
        return [] === $this->nonPrimitiveTypes && [] !== $this->scalarTypes;
    }

    public function setScalarTypes(array $scalarTypes): void
    {
        $this->scalarTypes = $scalarTypes;
    }

    public function getConstructorDefaultValue(): mixed
    {
        return $this->constructorDefaultValue;
    }

    public function setConstructorDefaultValue(mixed $constructorDefaultValue): void
    {
        $this->constructorDefaultValue = $constructorDefaultValue;
        $this->hasConstructorDefaultValue = true;
    }

    public function hasConstructorDefaultValue(): bool
    {
        return $this->hasConstructorDefaultValue;
    }
}
