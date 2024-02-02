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
 * This contains all necessary information about a class to create a custom Normalizer.
 * It has an array of PropertyDefinitions.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 7.1
 */
class ClassDefinition
{
    public const CONSTRUCTOR_NONE = 'none';
    public const CONSTRUCTOR_NON_PUBLIC = 'non_public';
    public const CONSTRUCTOR_PUBLIC = 'public';

    private string $sourceClassName;
    private string $namespaceAndClass;
    private string $newNamespace;
    private string $newClassName;
    private string $constructorType = self::CONSTRUCTOR_NONE;

    /**
     * @var PropertyDefinition[]
     */
    private array $definitions = [];

    public function __construct(string $namespaceAndClass, string $newClassName, string $newNamespace)
    {
        $this->namespaceAndClass = $namespaceAndClass;
        $this->newNamespace = $newNamespace;
        $this->newClassName = $newClassName;
        $this->sourceClassName = substr($namespaceAndClass, strrpos($namespaceAndClass, '\\') + 1);
    }

    public function getSourceClassName(): string
    {
        return $this->sourceClassName;
    }

    public function getNamespaceAndClass(): string
    {
        return $this->namespaceAndClass;
    }

    /**
     * @return PropertyDefinition[]
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    public function getDefinition(string $propertyName): ?PropertyDefinition
    {
        return $this->definitions[$propertyName] ?? null;
    }

    public function addDefinition(PropertyDefinition $definition): void
    {
        $this->definitions[$definition->getPropertyName()] = $definition;
    }

    public function getConstructorType(): string
    {
        return $this->constructorType;
    }

    public function setConstructorType(string $constructorType): void
    {
        $this->constructorType = $constructorType;
    }

    public function getNewNamespace(): string
    {
        return $this->newNamespace;
    }

    public function getNewClassName(): string
    {
        return $this->newClassName;
    }

    /**
     * @return PropertyDefinition[]
     */
    public function getConstructorArguments(): array
    {
        $arguments = [];
        foreach ($this->definitions as $def) {
            $order = $def->getConstructorArgumentOrder();
            if (null === $order) {
                continue;
            }
            $arguments[$order] = $def;
        }

        return $arguments;
    }
}
