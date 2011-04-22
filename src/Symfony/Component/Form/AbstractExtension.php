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
     * @var Boolean
     */
    private $typesLoaded = false;

    /**
     * @var FormTypeGuesserInterface
     */
    private $typeGuesser;

    /**
     * @var Boolean
     */
    private $typeGuesserLoaded = false;

    abstract protected function loadTypes();

    abstract protected function loadTypeGuesser();

    private function initTypes()
    {
        $this->typesLoaded = true;

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

    private function initTypeGuesser()
    {
        $this->typeGuesserLoaded = true;

        $guesser = $this->loadTypeGuesser();

        if (!$guesser instanceof FormTypeGuesserInterface) {
            throw new UnexpectedTypeException($type, 'Symfony\Component\Form\FormTypeGuesserInterface');
        }

        $this->guesser = $guesser;
    }

    public function getType($name)
    {
        if (!$this->typesLoaded) {
            $this->initTypes();
        }

        if (!isset($this->types[$name])) {
            throw new FormException(sprintf('The type "%s" can not be typesLoaded by this extension', $name));
        }

        return $this->types[$name];
    }

    public function hasType($name)
    {
        if (!$this->typesLoaded) {
            $this->initTypes();
        }

        return isset($this->types[$name]);
    }

    public function getTypeGuesser()
    {
        if (!$this->typeGuesserLoaded) {
            $this->initTypeGuesser();
        }

        return $this->typeGuesser;
    }
}
