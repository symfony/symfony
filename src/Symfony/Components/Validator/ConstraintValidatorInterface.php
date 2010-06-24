<?php

namespace Symfony\Components\Validator;

interface ConstraintValidatorInterface
{
    public function initialize(ValidationContext $context);

    public function isValid($value, Constraint $constraint);

    public function getMessageTemplate();

    public function getMessageParameters();
}