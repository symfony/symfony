<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

use Symfony\Component\Validator\Constraint;

class ConstraintC extends Constraint
{
    public $option1;

    public function requiredOptions()
    {
        return array('option1');
    }

    public function targets()
    {
        return array(self::PROPERTY_CONSTRAINT, self::CLASS_CONSTRAINT);
    }
}