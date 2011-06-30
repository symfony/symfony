<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

use Symfony\Component\Validator\Constraint;

/** @Annotation */
class ConstraintA extends Constraint
{
    public $property1;
    public $property2;

    public function getDefaultOption()
    {
        return 'property2';
    }

    public function getTargets()
    {
        return array(self::PROPERTY_CONSTRAINT, self::CLASS_CONSTRAINT);
    }
}
