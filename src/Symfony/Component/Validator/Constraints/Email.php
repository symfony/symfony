<?php

namespace Symfony\Component\Validator\Constraints;

class Email extends \Symfony\Component\Validator\Constraint
{
    public $message = 'Symfony.Validator.Email.message';
    public $checkMX = false;
}