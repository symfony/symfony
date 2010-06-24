<?php

namespace Symfony\Components\Validator\Constraints;

class Email extends \Symfony\Components\Validator\Constraint
{
    public $message = 'Symfony.Validator.Email.message';
    public $checkMX = false;
}