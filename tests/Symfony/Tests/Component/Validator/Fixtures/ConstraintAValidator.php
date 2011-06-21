<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ConstraintAValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        if ('VALID' != $value) {
            $this->setMessage('message', array('param' => 'value'));

            return false;
        }

        return true;
    }
}
