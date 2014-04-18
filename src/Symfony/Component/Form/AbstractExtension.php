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
     * The types provided by this extension
     * @var FormTypeInterface[] An array of FormTypeInterface
     */
    private $types;

    /**
     * The type extensions provided by this extension
     * @var FormTypeExtensionInterface[] An array of FormTypeExtensionInterface
     */
    private $typeExtensions;

    /**
     * The type guesser provided by this extension
     * @var FormTypeGuesserInterface
     */
    private $typeGuesser;

    /**
     * Whether the type guesser has been loaded
     * @var bool
     */
    private $typeGuesserLoaded = false;

    /**
     * {@inheritdoc}
     */
    public function getType($name)
    {
        if (null === $this->types) {
            $this->initTypes();
        }

        if (!isset($this->types[$name])) {
            throw new InvalidArgumentException(sprintf('The type "%s" can not be loaded by this extension', $name));
        }

        return $this->types[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function hasType($name)
    {
        if (null === $this->types) {
            $this->initTypes();
        }

        return isset($this->types[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeExtensions($name)
    {
        if (null === $this->typeExtensions) {
            $this->initTypeExtensions();
        }

        return isset($this->typeExtensions[$name])
            ? $this->typeExtensions[$name]
            : array();
    }

    /**
     * {@inheritdoc}
     */
    public function hasTypeExtensions($name)
    {
        if (null === $this->typeExtensions) {
            $this->initTypeExtensions();
        }

        return isset($this->typeExtensions[$name]) && count($this->typeExtensions[$name]) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeGuesser()
    {
        if (!$this->typeGuesserLoaded) {
            $this->initTypeGuesser();
        }

        return $this->typeGuesser;
    }

    /**
     * Registers the types.
     *
     * @return FormTypeInterface[] An array of FormTypeInterface instances
     */
    protected function loadTypes()
    {
        return array();
    }

    /**
     * Registers the type extensions.
     *
     * @return FormTypeExtensionInterface[] An array of FormTypeExtensionInterface instances
     */
    protected function loadTypeExtensions()
    {
        return array();
    }

    /**
     * Registers the type guesser.
     *
     * @return FormTypeGuesserInterface|null A type guesser
     */
    protected function loadTypeGuesser()
    {
    }

    /**
     * Initializes the types.
     *
     * @throws UnexpectedTypeException if any registered type is not an instance of FormTypeInterface
     */
    private function initTypes()
    {
        $this->types = array();

        foreach ($this->loadTypes() as $type) {
            if (!$type instanceof FormTypeInterface) {
                throw new UnexpectedTypeException($type, 'Symfony\Component\Form\FormTypeInterface');
            }

            $this->types[$type->getName()] = $type;
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
        $this->typeExtensions = array();

        foreach ($this->loadTypeExtensions() as $extension) {
            if (!$extension instanceof FormTypeExtensionInterface) {
                throw new UnexpectedTypeException($extension, 'Symfony\Component\Form\FormTypeExtensionInterface');
            }

            $type = $extension->getExtendedType();

            $this->typeExtensions[$type][] = $extension;
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
            throw new UnexpectedTypeException($this->typeGuesser, 'Symfony\Component\Form\FormTypeGuesserInterface');
        }
    }
}
