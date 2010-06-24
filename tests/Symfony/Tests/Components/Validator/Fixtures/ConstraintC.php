<?php

namespace Symfony\Tests\Components\Validator\Fixtures;

use Symfony\Components\Validator\Constraint;

class ConstraintC extends Constraint
{
    public $option1;

    public function requiredOptions()
    {
        return array('option1');
    }
}