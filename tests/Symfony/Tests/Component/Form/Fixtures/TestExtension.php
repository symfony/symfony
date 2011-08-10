<?php

namespace Symfony\Tests\Component\Form\Fixtures;

use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\FormExtensionInterface;

class TestExtension implements FormExtensionInterface
{
    private $types = array();

    private $extensions = array();

    private $guesser;

    public function __construct(FormTypeGuesserInterface $guesser)
    {
        $this->guesser = $guesser;
    }

    public function addType(FormTypeInterface $type)
    {
        $this->types[$type->getName()] = $type;
    }

    public function getType($name)
    {
        return isset($this->types[$name]) ? $this->types[$name] : null;
    }

    public function hasType($name)
    {
        return isset($this->types[$name]);
    }

    public function addTypeExtension(FormTypeExtensionInterface $extension)
    {
        $type = $extension->getExtendedType();

        if (!isset($this->extensions[$type])) {
            $this->extensions[$type] = array();
        }

        $this->extensions[$type][] = $extension;
    }

    public function getTypeExtensions($name)
    {
        return isset($this->extensions[$name]) ? $this->extensions[$name] : array();
    }

    public function hasTypeExtensions($name)
    {
        return isset($this->extensions[$name]);
    }

    public function getTypeGuesser()
    {
        return $this->guesser;
    }
}
