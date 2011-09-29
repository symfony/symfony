<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

use Symfony\Component\Validator\Constraint;

/** @Annotation */
class ConstraintC extends Constraint
{
    public $option1;

    public function getRequiredOptions()
    {
        return array('option1');
    }

    public function getTargets()
    {
        return array(self::PROPERTY_CONSTRAINT, self::CLASS_CONSTRAINT);
    }
}
