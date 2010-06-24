<?php

namespace Symfony\Components\Validator\Constraints;

class All extends \Symfony\Components\Validator\Constraint
{
    public $constraints = array();

    public function defaultOption()
    {
        return 'constraints';
    }

    public function requiredOptions()
    {
        return array('constraints');
    }
}