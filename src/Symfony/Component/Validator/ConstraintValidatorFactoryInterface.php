<?php

namespace Symfony\Component\Validator;

use Symfony\Component\Validator\Constraint;

interface ConstraintValidatorFactoryInterface
{
    public function getInstance(Constraint $constraint);
}