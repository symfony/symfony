<?php

namespace Symfony\Tests\Component\Form\Fixtures;

class Magician
{
    private $properties = array();

    public function __set($name, $value)
    {
        $this->properties[$name] = $value;
    }

    public function __get($name)
    {
        return isset($this->properties[$name]) ? $this->properties[$name] : null;
    }
}