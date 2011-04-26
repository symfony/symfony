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

use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
abstract class AbstractExtension implements FormExtensionInterface
{
    /**
     * @var array
     */
    private $types;

    /**
     * @var array
     */
    private $typeExtensions;

    /**
     * @var FormTypeGuesserInterface
     */
    private $typeGuesser;

    /**
     * @var Boolean
     */
    private $typeGuesserLoaded = false;

    protected function loadTypes()
    {
        return array();
    }

    protected function loadTypeExtensions()
    {
        return array();
    }

    protected function loadTypeGuesser()
    {
        return null;
    }

    private function initTypes()
    {
        $types = $this->loadTypes();
        $typesByName = array();

        foreach ($types as $type) {
            if (!$type instanceof FormTypeInterface) {
                throw new UnexpectedTypeException($type, 'Symfony\Component\Form\FormTypeInterface');
            }

            $typesByName[$type->getName()] = $type;
        }

        $this->types = $typesByName;
    }

    private function initTypeExtensions()
    {
        $extensions = $this->loadTypeExtensions();
        $extensionsByType = array();

        foreach ($extensions as $extension) {
            if (!$extension instanceof FormTypeExtensionInterface) {
                throw new UnexpectedTypeException($extension, 'Symfony\Component\Form\FormTypeExtensionInterface');
            }

            $type = $extension->getExtendedType();

            if (!isset($extensionsByType[$type])) {
                $extensionsByType[$type] = array();
            }

            $extensionsByType[$type][] = $extension;
        }

        $this->typeExtensions = $extensionsByType;
    }

    private function initTypeGuesser()
    {
        $this->typeGuesserLoaded = true;

        $guesser = $this->loadTypeGuesser();

        if (!$guesser instanceof FormTypeGuesserInterface) {
            throw new UnexpectedTypeException($guesser, 'Symfony\Component\Form\FormTypeGuesserInterface');
        }

        $this->guesser = $guesser;
    }

    public function getType($name)
    {
        if (null === $this->types) {
            $this->initTypes();
        }

        if (!isset($this->types[$name])) {
            throw new FormException(sprintf('The type "%s" can not be loaded by this extension', $name));
        }

        return $this->types[$name];
    }

    public function hasType($name)
    {
        if (null === $this->types) {
            $this->initTypes();
        }

        return isset($this->types[$name]);
    }

    function getTypeExtensions($name)
    {
        if (null === $this->typeExtensions) {
            $this->initTypeExtensions();
        }

        return isset($this->typeExtensions[$name])
            ? $this->typeExtensions[$name]
            : array();
    }

    function hasTypeExtensions($name)
    {
        if (null === $this->typeExtensions) {
            $this->initTypeExtensions();
        }

        return isset($this->typeExtensions[$name]) && count($this->typeExtensions[$name]) > 0;
    }

    public function getTypeGuesser()
    {
        if (!$this->typeGuesserLoaded) {
            $this->initTypeGuesser();
        }

        return $this->typeGuesser;
    }
}
