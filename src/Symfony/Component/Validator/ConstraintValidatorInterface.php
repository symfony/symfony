<?php

namespace Symfony\Component\Validator;

interface ConstraintValidatorInterface
{
    public function initialize(ValidationContext $context);

    public function isValid($value, Constraint $constraint);

    public function getMessageTemplate();

    public function getMessageParameters();
}