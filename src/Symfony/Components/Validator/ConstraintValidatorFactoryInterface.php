<?php

namespace Symfony\Components\Validator;

use Symfony\Components\Validator\Constraint;

interface ConstraintValidatorFactoryInterface
{
    public function getInstance(Constraint $constraint);
}