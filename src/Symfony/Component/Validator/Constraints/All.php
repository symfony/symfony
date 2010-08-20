<?php

namespace Symfony\Component\Validator\Constraints;

class All extends \Symfony\Component\Validator\Constraint
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