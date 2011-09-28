<?php

namespace Symfony\Tests\Component\Form\Fixtures;

class GetterSetter
{
    private $foobar;

    public function set($property, $value)
    {
        $this->$property = $value;
    }

    public function get($property)
    {
        return isset($this->$property) ? $this->$property : null;
    }
}