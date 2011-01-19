<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

use Symfony\Component\Validator\Constraint;

class PropertyConstraint extends Constraint
{
    public function targets()
    {
        return self::PROPERTY_CONSTRAINT;
    }
}