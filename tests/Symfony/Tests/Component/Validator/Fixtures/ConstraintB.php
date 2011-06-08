<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

use Symfony\Component\Validator\Constraint;

class ConstraintB extends Constraint {

    public function getTargets()
    {
        return array(self::PROPERTY_CONSTRAINT, self::CLASS_CONSTRAINT);
    }

}
