<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

use Symfony\Component\Validator\Constraint;

class ClassConstraint extends Constraint
{
    public function targets()
    {
        return self::CLASS_CONSTRAINT;
    }
}