<?php

namespace Symfony\Tests\Components\Validator\Fixtures;

use Symfony\Components\Validator\Constraint;

class ConstraintA extends Constraint
{
    public $property1;
    public $property2;

    public function defaultOption()
    {
        return 'property2';
    }
}