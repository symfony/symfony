<?php

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class FailingConstraintValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        $this->context->addViolation($constraint->message, array());

        return false;
    }
}
