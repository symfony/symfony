<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ConstraintAValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        if ($value != 'VALID') {
            $this->setMessage('message', array('param' => 'value'));
            return false;
        }

        return true;
    }
}