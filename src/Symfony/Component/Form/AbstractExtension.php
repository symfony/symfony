<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractExtension implements FormExtensionInterface
{
    /**
     * The types provided by this extension.
     *
     * @var FormTypeInterface[]
     */
    private array $types;

    /**
     * The type extensions provided by this extension.
     *
     * @var FormTypeExtensionInterface[][]
     */
    private array $typeExtensions;

    /**
     * The type guesser provided by this extension.
     */
    private $typeGuesser = null;

    /**
     * Whether the type guesser has been loaded.
     */
    private bool $typeGuesserLoaded = false;

    /**
     * {@inheritdoc}
     */
    public function getType(string $name): FormTypeInterface
    {
        if (!isset($this->types)) {
            $this->initTypes();
        }

        if (!isset($this->types[$name])) {
            throw new InvalidArgumentException(sprintf('The type "%s" cannot be loaded by this extension.', $name));
        }

        return $this->types[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function hasType(string $name): bool
    {
        if (!isset($this->types)) {
            $this->initTypes();
        }

        return isset($this->types[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeExtensions(string $name): array
    {
        if (!isset($this->typeExtensions)) {
            $this->initTypeExtensions();
        }

        return $this->typeExtensions[$name]
            ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function hasTypeExtensions(string $name): bool
    {
        if (!isset($this->typeExtensions)) {
            $this->initTypeExtensions();
        }

        return isset($this->typeExtensions[$name]) && \count($this->typeExtensions[$name]) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeGuesser(): ?FormTypeGuesserInterface
    {
        if (!$this->typeGuesserLoaded) {
            $this->initTypeGuesser();
        }

        return $this->typeGuesser;
    }

    /**
     * Registers the types.
     *
     * @return FormTypeInterface[]
     */
    protected function loadTypes()
    {
        return [];
    }

    /**
     * Registers the type extensions.
     *
     * @return FormTypeExtensionInterface[]
     */
    protected function loadTypeExtensions(): array
    {
        return [];
    }

    /**
     * Registers the type guesser.
     *
     * @return FormTypeGuesserInterface|null
     */
    protected function loadTypeGuesser()
    {
        return null;
    }

    /**
     * Initializes the types.
     *
     * @throws UnexpectedTypeException if any registered type is not an instance of FormTypeInterface
     */
    private function initTypes()
    {
        $this->types = [];

        foreach ($this->loadTypes() as $type) {
            if (!$type instanceof FormTypeInterface) {
                throw new UnexpectedTypeException($type, FormTypeInterface::class);
            }

            $this->types[\get_class($type)] = $type;
        }
    }

    /**
     * Initializes the type extensions.
     *
     * @throws UnexpectedTypeException if any registered type extension is not
     *                                 an instance of FormTypeExtensionInterface
     */
    private function initTypeExtensions()
    {
        $this->typeExtensions = [];

        foreach ($this->loadTypeExtensions() as $extension) {
            if (!$extension instanceof FormTypeExtensionInterface) {
                throw new UnexpectedTypeException($extension, FormTypeExtensionInterface::class);
            }

            foreach ($extension::getExtendedTypes() as $extendedType) {
                $this->typeExtensions[$extendedType][] = $extension;
            }
        }
    }

    /**
     * Initializes the type guesser.
     *
     * @throws UnexpectedTypeException if the type guesser is not an instance of FormTypeGuesserInterface
     */
    private function initTypeGuesser()
    {
        $this->typeGuesserLoaded = true;

        $this->typeGuesser = $this->loadTypeGuesser();
        if (null !== $this->typeGuesser && !$this->typeGuesser instanceof FormTypeGuesserInterface) {
            throw new UnexpectedTypeException($this->typeGuesser, FormTypeGuesserInterface::class);
        }
    }
}
