<?php

namespace Symfony\Component\Validator\Constraints;

class Email extends \Symfony\Component\Validator\Constraint
{
    public $message = 'This value is not a valid email address';
    public $checkMX = false;
}