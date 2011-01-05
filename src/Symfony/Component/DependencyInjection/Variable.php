<?php

namespace Symfony\Component\DependencyInjection;

class Variable
{
    protected $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function __toString()
    {
        return $this->name;
    }
}