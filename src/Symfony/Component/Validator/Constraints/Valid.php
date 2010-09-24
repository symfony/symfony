<?php

namespace Symfony\Component\Validator\Constraints;

class Valid extends \Symfony\Component\Validator\Constraint
{
    public $message = 'This value should be instance of class %class%';
    public $class;
}