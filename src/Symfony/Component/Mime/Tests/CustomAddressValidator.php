<?php

namespace Symfony\Component\Mime\Tests;

use Symfony\Component\Mime\EmailValidatorInterface;

class CustomAddressValidator implements EmailValidatorInterface
{
    public function isValid(string $email, mixed $emailValidation): bool
    {
        return true;
    }
}
