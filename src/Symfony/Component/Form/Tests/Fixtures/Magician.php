<?php

namespace Symfony\Component\Form\Tests\Fixtures;

class Magician
{
    private $foobar;

    public function __set($property, $value)
    {
        $this->$property = $value;
    }

    public function __get($property)
    {
        return isset($this->$property) ? $this->$property : null;
    }
}
