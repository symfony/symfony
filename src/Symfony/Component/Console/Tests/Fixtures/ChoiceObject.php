<?php

class ChoiceObject
{
    private $env;

    public function __construct($env)
    {
        $this->env = $env;
    }

    public function __toString()
    {
        return $this->env;
    }
}
