<?php

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ExecutionContext;

class ConstraintAValidator extends ConstraintValidator
{
    static public $passedContext;

    public function initialize(ExecutionContext $context)
    {
        parent::initialize($context);

        self::$passedContext = $context;
    }

    public function isValid($value, Constraint $constraint)
    {
        if ('VALID' != $value) {
            $this->context->addViolation('message', array('param' => 'value'));

            return false;
        }

        return true;
    }
}
