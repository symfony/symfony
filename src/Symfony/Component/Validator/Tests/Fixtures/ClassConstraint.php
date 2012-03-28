<?php

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\Constraint;

class ClassConstraint extends Constraint
{
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
