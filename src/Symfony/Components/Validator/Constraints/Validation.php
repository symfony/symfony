<?php

namespace Symfony\Components\Validator\Constraints;

class Validation
{
    public $constraints;

    public function __construct(array $constraints)
    {
        $this->constraints = $constraints['value'];
    }
}
