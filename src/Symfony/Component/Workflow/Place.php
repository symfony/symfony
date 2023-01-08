<?php

namespace Symfony\Component\Workflow;

class Place
{
    public function __construct(private string|\UnitEnum $value)
    {
    }

    public function name(): string
    {
        if($this->value instanceof \UnitEnum) {
            return $this->value->name;
        }

        return $this->value;
    }
}
