<?php

namespace Symfony\Component\Mime;

interface EmailValidatorInterface
{
    public function isValid(string $email, mixed $emailValidation): bool;
}
